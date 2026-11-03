<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\TemplateOption;
use App\Models\TreasuryTransaction;
use App\Support\Accounting\Periods;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TreasuryPostingService
{
    public function openingBalance(array $data): OpeningBalance
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            // Same exclusivity as Saldo Neraca / Setoran Modal Awal: one opening per branch + period.
            OpeningPositionService::ensureFree($data['company_id'], $data['branch_id'], $data['fiscal_period_id']);
            if (OpeningBalance::where('company_id', $data['company_id'])
                ->where('fiscal_period_id', $data['fiscal_period_id'])
                ->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Duplicate opening balance.');
            }

            $opening = OpeningBalance::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Opening balance',
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $entry = app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Opening balance',
                'source_type' => self::class,
                'source_kind' => 'opening',
                'source_id' => (string) $opening->id,
                'lines' => $data['lines'],
            ]);

            $opening->update(['journal_entry_id' => $entry->id]);

            return $opening->fresh('journalEntry');
        });
    }

    public function cashMovement(array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $cash = CashAccount::where('company_id', $data['company_id'])->findOrFail($data['cash_account_id']);
            $transaction = TreasuryTransaction::create([
                ...$data,
                'cash_account_id' => $cash->id,
                'status' => 'posted',
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Cash movement',
                'source_type' => self::class,
                'source_kind' => 'treasury',
                'source_id' => (string) $transaction->id,
                'lines' => $data['lines'],
            ]);

            return $transaction->load('journalEntry.lines');
        });
    }

    public function bankTransfer(array $data): TreasuryTransaction
    {
        return $this->transfer($data);
    }

    public function transfer(array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            if ($data['from_account_id'] === $data['to_account_id']) {
                throw new InvalidArgumentException('Transfer accounts must be different.');
            }

            $accounts = CashAccount::where('company_id', $data['company_id'])
                ->whereIn('id', [$data['from_cash_account_id'], $data['to_cash_account_id']])
                ->lockForUpdate()->get();
            if ($accounts->count() !== 2 || (string) $data['amount'] <= '0') {
                throw new InvalidArgumentException('Invalid cash transfer.');
            }

            $transaction = TreasuryTransaction::create([
                ...$data,
                'cash_account_id' => $data['from_cash_account_id'],
                'transaction_type' => 'transfer',
                'status' => 'posted',
                'description' => $data['description'] ?? 'Cash transfer',
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Cash transfer',
                'source_type' => self::class,
                'source_kind' => 'treasury',
                'source_id' => (string) $transaction->id,
                'lines' => [
                    ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                    ['account_id' => $data['from_account_id'], 'debit' => '0', 'credit' => $data['amount']],
                ],
            ]);

            $transaction->update([
                'journal_entry_id' => JournalEntry::query()
                    ->where('company_id', $data['company_id'])
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->value('id'),
            ]);

            return $transaction->load('journalEntry.lines');
        });
    }

    /**
     * Post one cash/bank movement (PRD 03 #7). Dr the Ke account, Cr the Dari account, for the same amount.
     * Accounts must belong to the categories of the chosen Template Jurnal type (sections 8-9).
     * "Bayar hutang" also settles open expenses FIFO on that payable (ADR-011) without a second journal.
     *
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, posted_on:string, transaction_type:string, from_account_id:int, to_account_id:int, amount:string, description:string, idempotency_key:string, created_by?:int}  $data
     */
    public function move(array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($data) {
            $companyId = (int) $data['company_id'];
            $postedOn = (string) $data['posted_on'];
            Branch::where('company_id', $companyId)->findOrFail($data['branch_id']);
            $period = FiscalPeriod::where('company_id', $companyId)->findOrFail($data['fiscal_period_id']);
            if ($period->status !== 'open' || $postedOn < $period->starts_on->toDateString() || $postedOn > $period->ends_on->toDateString()) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal ini.');
            }
            if (TreasuryTransaction::where('company_id', $companyId)->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Transaksi ini sudah diposting.');
            }

            $amount = bcadd((string) $data['amount'], '0', 2);
            if (bccomp($amount, '0', 2) <= 0) {
                throw new InvalidArgumentException('Nilai harus lebih dari nol.');
            }
            if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
                throw new InvalidArgumentException('Akun Dari dan akun Ke harus berbeda.');
            }

            $type = TemplateOption::where([
                'company_id' => $companyId,
                'group' => 'treasury_type',
                'code' => $data['transaction_type'],
                'is_active' => true,
            ])->first();
            if (! $type) {
                throw new InvalidArgumentException('Jenis transaksi tidak tersedia. Atur di Template Jurnal bagian 8.');
            }

            $from = $this->activeAccount($companyId, (int) $data['from_account_id']);
            $to = $this->activeAccount($companyId, (int) $data['to_account_id']);
            $this->assertCategory($companyId, (string) ($type->meta['from_category'] ?? ''), $from, 'Akun Dari tidak termasuk kategori jenis transaksi ini.');
            $this->assertCategory($companyId, (string) ($type->meta['to_category'] ?? ''), $to, 'Akun Ke tidak termasuk kategori jenis transaksi ini.');

            if ($data['transaction_type'] === 'bayar_hutang') {
                Account::where('company_id', $companyId)->whereKey($to->id)->lockForUpdate()->first();
                $open = Expense::where('company_id', $companyId)
                    ->where('branch_id', $data['branch_id'])
                    ->where('settlement_account_id', $to->id)
                    ->where('status', '!=', 'reversed')
                    ->orderBy('expense_date')->orderBy('id')
                    ->lockForUpdate()->get();
                $payable = $this->payableBalance($companyId, (int) $data['branch_id'], $to);
                if (bccomp($amount, $payable, 2) > 0) {
                    throw new InvalidArgumentException('Pembayaran melebihi saldo hutang di buku besar.');
                }
            } else {
                $open = null;
            }

            $reference = $this->nextReference($companyId, $postedOn);
            $transaction = TreasuryTransaction::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'transacted_on' => $postedOn,
                'posted_on' => $postedOn,
                'transaction_type' => $data['transaction_type'],
                'status' => 'posted',
                'amount' => $amount,
                'description' => $data['description'],
                'reference' => $reference,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $entry = app(JournalPostingService::class)->post([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'posted_on' => $postedOn,
                'description' => $data['description'],
                'idempotency_key' => $data['idempotency_key'],
                'source_type' => self::class,
                'source_kind' => 'treasury',
                'source_id' => (string) $transaction->id,
                'reference' => $reference,
                'created_by' => $data['created_by'] ?? null,
                'lines' => [
                    ['account_id' => $to->id, 'debit' => $amount, 'credit' => '0'],
                    ['account_id' => $from->id, 'debit' => '0', 'credit' => $amount],
                ],
            ]);
            $transaction->update(['journal_entry_id' => $entry->id]);

            if ($open !== null) {
                $this->allocateFifo($transaction, $open, $from->id, $to->id, $amount, $postedOn, (string) $data['idempotency_key']);
            }

            return $transaction->fresh(['journalEntry.lines']);
        });
    }

    /**
     * Correct a posted movement by reversing its journal. FIFO allocations are voided and expense
     * statuses return to whatever is still unpaid. The original journal is kept.
     *
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverse(TreasuryTransaction $transaction, array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $transaction = TreasuryTransaction::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status === 'reversed' || $transaction->reversed_at) {
                throw new InvalidArgumentException('Transaksi kas/bank ini sudah dibalik.');
            }
            if (! $transaction->journal_entry_id) {
                throw new InvalidArgumentException('Transaksi tidak memiliki jurnal.');
            }

            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $transaction->posted_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal transaksi.');
            }
            $period = Periods::openFor((int) $data['company_id'], $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }

            $reversal = app(JournalReversalService::class)->reverse(
                JournalEntry::where('company_id', $data['company_id'])->findOrFail($transaction->journal_entry_id),
                [
                    'posted_on' => $postedOn,
                    'fiscal_period_id' => $period->id,
                    'description' => 'Pembalikan kas/bank '.$transaction->reference.': '.$data['reason'],
                    'idempotency_key' => 'treasury-reversal-'.$transaction->id,
                    'created_by' => $data['created_by'] ?? null,
                ],
            );

            $payments = ExpensePayment::where('treasury_transaction_id', $transaction->id)->whereNull('reversed_at')->lockForUpdate()->get();
            if ($payments->isNotEmpty()) {
                ExpensePayment::whereIn('id', $payments->pluck('id'))->update(['reversed_at' => now()]);
            }

            $transaction->update([
                'status' => 'reversed',
                'reversal_journal_entry_id' => $reversal->id,
                'reversal_reason' => $data['reason'],
                'reversed_at' => now(),
            ]);

            foreach (Expense::whereIn('id', $payments->pluck('expense_id')->unique())->lockForUpdate()->get() as $expense) {
                $this->refreshExpenseStatus($expense);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Normal-side balance of each account, per branch. Missing postings are zero.
     *
     * @param  array<int, int>  $accountIds
     * @param  array<int, int>  $branchIds
     * @return array<int, array{branch_id:int, account_id:int, balance:string}>
     */
    public function positions(int $companyId, array $accountIds, array $branchIds): array
    {
        if ($accountIds === [] || $branchIds === []) {
            return [];
        }

        $accounts = Account::where('company_id', $companyId)->whereIn('id', $accountIds)->get()->keyBy('id');
        $nets = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)
            ->whereIn('e.branch_id', $branchIds)
            ->whereIn('l.account_id', $accountIds)
            ->groupBy('e.branch_id', 'l.account_id')
            ->selectRaw('e.branch_id, l.account_id, SUM(l.debit - l.credit) as net')
            ->get()
            ->keyBy(fn ($row) => $row->branch_id.'-'.$row->account_id);

        $out = [];
        foreach ($branchIds as $branchId) {
            foreach ($accountIds as $accountId) {
                $account = $accounts[$accountId] ?? null;
                if (! $account) {
                    continue;
                }
                $net = LedgerService::money($nets[$branchId.'-'.$accountId]->net ?? 0);
                $out[] = [
                    'branch_id' => (int) $branchId,
                    'account_id' => (int) $accountId,
                    'balance' => LedgerService::normalSigned($net, $account->type, (bool) $account->is_contra),
                ];
            }
        }

        return $out;
    }

    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])
            ->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
        }
    }

    private function activeAccount(int $companyId, int $accountId): Account
    {
        $account = Account::where('company_id', $companyId)->where('is_active', true)->find($accountId);
        if (! $account) {
            throw new InvalidArgumentException('Akun tidak ditemukan atau tidak aktif.');
        }

        return $account;
    }

    private function assertCategory(int $companyId, string $category, Account $account, string $message): void
    {
        $allowed = TemplateOption::where([
            'company_id' => $companyId,
            'group' => 'treasury_category',
            'account_id' => $account->id,
            'is_active' => true,
        ])->get()->contains(fn (TemplateOption $option) => ($option->meta['category'] ?? null) === $category);

        if ($category === '' || ! $allowed) {
            throw new InvalidArgumentException($message);
        }
    }

    /** Credit-normal balance of a payable, for this branch, before the payment is posted. */
    private function payableBalance(int $companyId, int $branchId, Account $account): string
    {
        $net = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)
            ->where('e.branch_id', $branchId)
            ->where('l.account_id', $account->id)
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as net')
            ->value('net');

        return LedgerService::normalSigned(LedgerService::money($net), $account->type, (bool) $account->is_contra);
    }

    private function nextReference(int $companyId, string $postedOn): string
    {
        $prefix = 'KAS-'.substr($postedOn, 0, 4).'-';
        $last = TreasuryTransaction::where('company_id', $companyId)->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    /** @param  Collection<int, Expense>  $expenses */
    private function allocateFifo(TreasuryTransaction $transaction, $expenses, int $cashAccountId, int $payableAccountId, string $amount, string $postedOn, string $idempotencyKey): void
    {
        $left = $amount;
        foreach ($expenses as $expense) {
            if (bccomp($left, '0', 2) <= 0) {
                break;
            }
            $paid = LedgerService::money($expense->payments()->whereNull('reversed_at')->sum('amount'));
            $remain = bcsub((string) $expense->amount, $paid, 2);
            if (bccomp($remain, '0', 2) <= 0) {
                continue;
            }
            $slice = bccomp($left, $remain, 2) >= 0 ? $remain : $left;
            ExpensePayment::create([
                'company_id' => $transaction->company_id,
                'branch_id' => $transaction->branch_id,
                'fiscal_period_id' => $transaction->fiscal_period_id,
                'expense_id' => $expense->id,
                'treasury_transaction_id' => $transaction->id,
                'cash_account_id' => $cashAccountId,
                'payable_account_id' => $payableAccountId,
                'journal_entry_id' => $transaction->journal_entry_id,
                'paid_on' => $postedOn,
                'amount' => $slice,
                'idempotency_key' => $idempotencyKey.':'.$expense->id,
            ]);
            $newPaid = bcadd($paid, $slice, 2);
            $expense->update(['status' => bccomp($newPaid, (string) $expense->amount, 2) >= 0 ? 'paid' : 'partial']);
            $left = bcsub($left, $slice, 2);
        }
    }

    private function refreshExpenseStatus(Expense $expense): void
    {
        if ($expense->status === 'reversed') {
            return;
        }
        $paid = LedgerService::money($expense->payments()->whereNull('reversed_at')->sum('amount'));
        $status = match (true) {
            bccomp($paid, '0', 2) === 0 => 'open',
            bccomp($paid, (string) $expense->amount, 2) >= 0 => 'paid',
            default => 'partial',
        };
        $expense->update(['status' => $status]);
    }
}
