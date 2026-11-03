<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FixedAsset;
use App\Models\InventoryMovement;
use App\Models\OpeningBalanceLine;
use App\Models\TreasuryTransaction;
use App\Support\Csv;
use Illuminate\Support\Carbon;

/**
 * Laporan Daftar Hutang (PRD 05 #9). Documents that exist today: expenses, inventory
 * and asset credit purchases, opening liabilities, and loan receipts. Payments that FIFO
 * did not attach to an expense reduce the oldest other document on the same account.
 * Whatever the ledger still holds after that is one "tanpa dokumen" row per account.
 */
class PayableReportService
{
    public const COLUMNS = [
        'Cabang', 'Keterangan', 'Akun Hutang', 'Tanggal', 'Nilai', 'Dibayar', 'Sisa', 'Status', 'Umur', 'Bayar Terakhir', 'Sumber',
    ];

    /**
     * @param  array{company_id:int, branch_id?:?int, account_id?:?int, as_of?:?string, status?:?string}  $filters
     * @return array{rows:array<int, array<string, mixed>>, reconciliation:array{documents:string, undocumented:string, ledger:string}}
     */
    public function build(array $filters): array
    {
        $companyId = (int) $filters['company_id'];
        $asOf = $filters['as_of'] ?? now()->toDateString();
        $branchId = $filters['branch_id'] ?? null;
        $accountId = $filters['account_id'] ?? null;
        $status = $filters['status'] ?? 'open';

        $docs = $this->documents($companyId, $branchId, $accountId, $asOf);
        $this->applyUnallocated($companyId, $branchId, $asOf, $docs);

        $branches = $branchId ? [$branchId] : Branch::where('company_id', $companyId)->pluck('id')->all();
        $docAccountIds = array_values(array_unique(array_column($docs, 'account_id')));
        $accounts = Account::where('company_id', $companyId)->where('type', 'liability')
            ->where(function ($q) use ($accountId, $docAccountIds) {
                if ($accountId) {
                    $q->whereKey($accountId);

                    return;
                }
                $q->whereIn('module', ['Hutang', 'Pinjaman'])->orWhereIn('id', $docAccountIds);
            })
            ->get(['id', 'code', 'name', 'type', 'is_contra'])->keyBy('id');

        $ledger = '0.00';
        $undocumented = '0.00';
        foreach ($branches as $branch) {
            $nets = app(BalanceSheetService::class)->nets($companyId, (int) $branch, $asOf);
            foreach ($accounts as $account) {
                $gl = LedgerService::normalSigned($nets[$account->id] ?? '0.00', $account->type, (bool) $account->is_contra);
                $ledger = bcadd($ledger, $gl, 2);
                $held = '0.00';
                foreach ($docs as $doc) {
                    if ($doc['branch_id'] === (int) $branch && $doc['account_id'] === $account->id) {
                        $held = bcadd($held, $doc['remaining'], 2);
                    }
                }
                $gap = bcsub($gl, $held, 2);
                $undocumented = bcadd($undocumented, $gap, 2);
                if (bccomp($gap, '0', 2) > 0) {
                    $docs[] = $this->gap($account, (int) $branch, $gap, $asOf);
                }
            }
        }

        $rows = [];
        $documentRemaining = '0.00';
        foreach ($docs as $doc) {
            $open = bccomp($doc['remaining'], '0', 2) > 0;
            if ($doc['source'] !== 'Tanpa dokumen') {
                $documentRemaining = bcadd($documentRemaining, $doc['remaining'], 2);
            }
            if ($status === 'open' && ! $open) {
                continue;
            }
            if ($status === 'paid' && $open) {
                continue;
            }
            $rows[] = $doc;
        }
        usort($rows, fn ($a, $b) => [$a['date'] ?? '9999', $a['sort_id']] <=> [$b['date'] ?? '9999', $b['sort_id']]);

        return [
            'rows' => array_map(fn ($row) => array_diff_key($row, array_flip(['branch_id', 'account_id', 'sort_id'])), $rows),
            'reconciliation' => [
                'documents' => $documentRemaining,
                'undocumented' => $undocumented,
                'ledger' => $ledger,
            ],
        ];
    }

    /**
     * Open document rows for the dashboard, shaped like the previous expense list.
     *
     * @return array<int, array{id:int, number:string, party:?string, date:?string, amount:string}>
     */
    public function top(int $companyId, ?int $branchId, string $asOf): array
    {
        $built = $this->build([
            'company_id' => $companyId, 'branch_id' => $branchId, 'as_of' => $asOf, 'status' => 'open',
        ]);
        $rows = array_values(array_filter($built['rows'], fn ($row) => $row['source'] !== 'Tanpa dokumen'));
        usort($rows, fn ($a, $b) => bccomp($b['remaining'], $a['remaining'], 2));

        return array_map(fn ($row) => [
            'id' => $row['source_id'],
            'number' => $row['number'],
            'party' => $row['party'],
            'date' => $row['date'],
            'amount' => $row['remaining'],
        ], array_slice($rows, 0, 5));
    }

    /** @param  array<string, mixed>  $row */
    public static function csvRow(array $row): array
    {
        return [
            Csv::cell($row['branch']), Csv::cell($row['description']), Csv::cell($row['account']), $row['date'] ?? '',
            $row['nilai'], $row['paid'], $row['remaining'], $row['status'], $row['age'], $row['last_payment'] ?? '',
            $row['source'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function documents(int $companyId, ?int $branchId, ?int $accountId, string $asOf): array
    {
        $docs = [];
        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', 'reversed')
            ->whereDate('expense_date', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($accountId, fn ($q, $id) => $q->where('settlement_account_id', $id))
            ->whereHas('settlementAccount', fn ($q) => $q->where('type', 'liability'))
            ->with(['branch:id,name', 'vendor:id,name', 'settlementAccount:id,code,name'])
            ->with(['payments' => fn ($q) => $q->whereNull('reversed_at')->whereDate('paid_on', '<=', $asOf)])
            ->get();
        foreach ($expenses as $expense) {
            $paid = '0.00';
            $last = null;
            foreach ($expense->payments as $payment) {
                $paid = bcadd($paid, LedgerService::money($payment->amount), 2);
                $on = $payment->paid_on->toDateString();
                if ($last === null || $on > $last) {
                    $last = $on;
                }
            }
            $docs[] = $this->present($expense->branch?->name, $expense->description ?: ($expense->reference ?: 'Beban'), $expense->settlementAccount, $expense->expense_date->toDateString(), LedgerService::money($expense->amount), $paid, $last, 'Beban', '/transactions/expenses/'.$expense->id, (int) $expense->branch_id, (int) $expense->settlement_account_id, $expense->id, $expense->reference ?: 'EXP-'.$expense->id, $expense->vendor?->name, $asOf);
        }

        $purchases = InventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('movement_type', 'purchase')
            ->whereNull('reversed_at')
            ->whereDate('moved_on', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($accountId, fn ($q, $id) => $q->where('settlement_account_id', $id))
            ->whereHas('settlementAccount', fn ($q) => $q->where('type', 'liability'))
            ->with(['branch:id,name', 'item:id,name', 'settlementAccount:id,code,name'])
            ->get();
        foreach ($purchases as $purchase) {
            $docs[] = $this->present($purchase->branch?->name, $purchase->description ?: ($purchase->item?->name ?: 'Persediaan'), $purchase->settlementAccount, $purchase->moved_on->toDateString(), LedgerService::money($purchase->total_cost), '0.00', null, 'Persediaan', '/data/d-persediaan?search='.rawurlencode((string) $purchase->reference), (int) $purchase->branch_id, (int) $purchase->settlement_account_id, $purchase->id, $purchase->reference, null, $asOf);
        }

        $assetPurchases = FixedAsset::query()
            ->where('company_id', $companyId)
            ->where('source', 'purchase')
            ->whereNull('reversed_at')
            ->whereDate('acquired_on', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($accountId, fn ($q, $id) => $q->where('settlement_account_id', $id))
            ->whereHas('settlementAccount', fn ($q) => $q->where('type', 'liability'))
            ->with(['branch:id,name', 'settlementAccount:id,code,name'])
            ->get();
        foreach ($assetPurchases as $asset) {
            $docs[] = $this->present($asset->branch?->name, $asset->description ?: $asset->name, $asset->settlementAccount, $asset->acquired_on->toDateString(), LedgerService::money($asset->cost), '0.00', null, 'Asset', '/data/d-asset?search='.rawurlencode((string) $asset->code), (int) $asset->branch_id, (int) $asset->settlement_account_id, $asset->id, $asset->code, null, $asOf);
        }

        $lines = OpeningBalanceLine::query()
            ->where('side', 'credit')
            ->whereHas('openingBalance', fn ($q) => $q->where('company_id', $companyId)->whereNull('reversed_at')->whereDate('posted_on', '<=', $asOf)
                ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id)))
            ->when($accountId, fn ($q, $id) => $q->where('account_id', $id))
            ->whereHas('account', fn ($q) => $q->where('type', 'liability'))
            ->with(['account:id,code,name', 'openingBalance.branch:id,name'])
            ->get();
        foreach ($lines as $line) {
            $opening = $line->openingBalance;
            $source = $opening->method === OpeningPositionService::MODAL_AWAL ? 'Modal awal' : 'Saldo neraca';
            $docs[] = $this->present($opening->branch?->name, $line->description ?: $source, $line->account, $opening->posted_on->toDateString(), LedgerService::money($line->amount), '0.00', null, $source, $source === 'Modal awal' ? '/data/d-modal' : '/inputan/saldo', (int) $opening->branch_id, (int) $line->account_id, $opening->id, $source, null, $asOf);
        }

        $loans = TreasuryTransaction::query()
            ->where('company_id', $companyId)
            ->where('transaction_type', 'terima_pinjaman')
            ->whereNull('reversed_at')
            ->whereDate('posted_on', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($accountId, fn ($q, $id) => $q->where('from_account_id', $id))
            ->whereHas('fromAccount', fn ($q) => $q->where('type', 'liability'))
            ->with(['branch:id,name', 'fromAccount:id,code,name'])
            ->get();
        foreach ($loans as $loan) {
            $docs[] = $this->present($loan->branch?->name, $loan->description ?: $loan->reference, $loan->fromAccount, $loan->posted_on->toDateString(), LedgerService::money($loan->amount), '0.00', null, 'Pinjaman', '/data/d-kasbank?search='.rawurlencode((string) $loan->reference), (int) $loan->branch_id, (int) $loan->from_account_id, $loan->id, $loan->reference, null, $asOf);
        }

        return $docs;
    }

    /** @param  list<array<string, mixed>>  $docs */
    private function applyUnallocated(int $companyId, ?int $branchId, string $asOf, array &$docs): void
    {
        $allocated = ExpensePayment::query()
            ->where('company_id', $companyId)
            ->whereNull('reversed_at')
            ->whereDate('paid_on', '<=', $asOf)
            ->whereNotNull('treasury_transaction_id')
            ->selectRaw('treasury_transaction_id, SUM(amount) as paid')
            ->groupBy('treasury_transaction_id')
            ->pluck('paid', 'treasury_transaction_id');

        $payments = TreasuryTransaction::query()
            ->where('company_id', $companyId)
            ->where('transaction_type', 'bayar_hutang')
            ->whereNull('reversed_at')
            ->whereDate('posted_on', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('posted_on')->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $left = bcsub(LedgerService::money($payment->amount), LedgerService::money($allocated[$payment->id] ?? 0), 2);
            if (bccomp($left, '0', 2) <= 0) {
                continue;
            }
            $order = array_keys($docs);
            usort($order, fn ($a, $b) => [$docs[$a]['date'], $docs[$a]['sort_id']] <=> [$docs[$b]['date'], $docs[$b]['sort_id']]);
            foreach ($order as $index) {
                if (bccomp($left, '0', 2) <= 0) {
                    break;
                }
                $doc = &$docs[$index];
                if ($doc['source'] === 'Beban' || $doc['branch_id'] !== (int) $payment->branch_id || $doc['account_id'] !== (int) $payment->to_account_id) {
                    continue;
                }
                if (bccomp($doc['remaining'], '0', 2) <= 0) {
                    continue;
                }
                $slice = bccomp($left, $doc['remaining'], 2) >= 0 ? $doc['remaining'] : $left;
                $doc['paid'] = bcadd($doc['paid'], $slice, 2);
                $doc['remaining'] = bcsub($doc['remaining'], $slice, 2);
                $doc['last_payment'] = $payment->posted_on->toDateString();
                $doc['status'] = $this->status($doc['paid'], $doc['remaining']);
                $left = bcsub($left, $slice, 2);
                unset($doc);
            }
        }
    }

    /** @return array<string, mixed> */
    private function present(?string $branch, string $description, ?Account $account, string $date, string $nilai, string $paid, ?string $last, string $source, ?string $url, int $branchId, int $accountId, int $sourceId, ?string $number, ?string $party, string $asOf): array
    {
        $remaining = bcsub($nilai, $paid, 2);
        $days = (int) Carbon::parse($date)->startOfDay()->diffInDays(Carbon::parse($asOf)->startOfDay());

        return [
            'branch' => $branch,
            'description' => $description,
            'account' => $account ? $account->code.' — '.$account->name : null,
            'date' => $date,
            'nilai' => $nilai,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $this->status($paid, $remaining),
            'age' => $days.' ('.($days <= 30 ? '≤30' : ($days <= 60 ? '31–60' : '>60')).')',
            'last_payment' => $last,
            'source' => $source,
            'url' => $url,
            'branch_id' => $branchId,
            'account_id' => $accountId,
            'source_id' => $sourceId,
            'number' => $number,
            'party' => $party,
            'sort_id' => $sourceId,
        ];
    }

    /** @return array<string, mixed> */
    private function gap(Account $account, int $branchId, string $amount, string $asOf): array
    {
        $branch = Branch::find($branchId);

        return [
            'branch' => $branch?->name,
            'description' => 'Saldo tanpa dokumen (aset, persediaan, atau jurnal manual)',
            'account' => $account->code.' — '.$account->name,
            'date' => null,
            'nilai' => $amount,
            'paid' => '0.00',
            'remaining' => $amount,
            'status' => 'Belum',
            'age' => '—',
            'last_payment' => null,
            'source' => 'Tanpa dokumen',
            'url' => null,
            'branch_id' => $branchId,
            'account_id' => $account->id,
            'source_id' => 0,
            'number' => 'Tanpa dokumen',
            'party' => null,
            'sort_id' => PHP_INT_MAX,
        ];
    }

    private function status(string $paid, string $remaining): string
    {
        if (bccomp($remaining, '0', 2) <= 0) {
            return 'Lunas';
        }

        return bccomp($paid, '0', 2) > 0 ? 'Sebagian' : 'Belum';
    }
}
