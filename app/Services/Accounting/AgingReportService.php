<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Invoice;
use App\Support\Csv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Piutang (PRD 05 #8). One row per invoice still on the books at the as-of date.
 * Aging buckets follow the reference: <=30, 31-60, and older than 60 days.
 * Amounts stay bcmath strings. The default status is open so a reversed invoice disappears.
 */
class AgingReportService
{
    public const COLUMNS = [
        'Cabang', 'Customer', 'Invoice', 'Tanggal', 'Total Tagihan', 'Dibayar', 'Sisa', 'Status', 'Umur Hari', 'Bayar Terakhir',
    ];

    /**
     * Outstanding invoices only. Kept for callers that just need the open list.
     *
     * @param  array{company_id?:int, branch_id?:?int, customer_id?:?int, as_of?:?string, status?:?string}  $filters
     */
    public function receivables(array $filters = []): Collection
    {
        $filters['status'] = $filters['status'] ?? 'open';

        return collect($this->build($filters)['rows']);
    }

    /**
     * @param  array{company_id?:int, branch_id?:?int, customer_id?:?int, as_of?:?string, status?:?string}  $filters
     * @return array{rows:array<int, array<string, mixed>>, buckets:array<string, string>, reconciliation:array{documents:string, ledger:string, difference:string}, as_of:string}
     */
    public function build(array $filters = []): array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $asOf = $filters['as_of'] ?? now()->toDateString();
        $status = $filters['status'] ?? 'open';
        $branchId = $filters['branch_id'] ?? null;

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', 'reversed')
            ->whereDate('issued_on', '<=', $asOf)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->with(['customer:id,name', 'branch:id,name'])
            ->with(['receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->whereNull('reversed_at')->whereDate('received_on', '<=', $asOf))->with('receipt:id,receipt_number,received_on')])
            ->orderBy('issued_on')->orderBy('id')
            ->get();

        $rows = [];
        $buckets = ['≤30' => '0.00', '31–60' => '0.00', '>60' => '0.00'];
        $openRemaining = '0.00';
        foreach ($invoices as $invoice) {
            $paid = '0.00';
            $last = null;
            $plt = null;
            foreach ($invoice->receiptAllocations as $allocation) {
                $paid = bcadd($paid, LedgerService::money($allocation->amount), 2);
                $on = $allocation->receipt?->received_on?->toDateString();
                if ($on && ($last === null || $on > $last)) {
                    $last = $on;
                }
                $number = $allocation->receipt?->receipt_number;
                if ($number && str_starts_with($number, 'PLT-')) {
                    $plt = $number;
                }
            }
            $total = LedgerService::money($invoice->total);
            $remaining = bcsub($total, $paid, 2);
            if (bccomp($remaining, '0', 2) < 0) {
                $remaining = '0.00';
            }
            $open = bccomp($remaining, '0', 2) > 0;
            $days = (int) Carbon::parse($invoice->issued_on)->startOfDay()->diffInDays(Carbon::parse($asOf)->startOfDay());
            $bucket = $days <= 30 ? '≤30' : ($days <= 60 ? '31–60' : '>60');
            if ($open) {
                $buckets[$bucket] = bcadd($buckets[$bucket], $remaining, 2);
                $openRemaining = bcadd($openRemaining, $remaining, 2);
            }
            if ($status === 'open' && ! $open) {
                continue;
            }
            if ($status === 'paid' && $open) {
                continue;
            }
            $rows[] = [
                'id' => $invoice->id,
                'branch' => $invoice->branch?->name,
                'customer' => $invoice->customer?->name,
                'number' => $invoice->invoice_number,
                'date' => $invoice->issued_on->toDateString(),
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'outstanding' => $remaining,
                'status' => $open ? (bccomp($paid, '0', 2) > 0 ? 'Sebagian' : 'Belum') : 'Lunas',
                'days' => $days,
                'bucket' => $bucket,
                'last_payment' => $last,
                'invoice_url' => '/transactions/invoices/'.$invoice->id,
                'receipt_url' => $plt ? '/data/d-piutang?search='.rawurlencode($plt) : null,
            ];
        }

        $ledger = $this->ledger($companyId, $branchId, $asOf);

        return [
            'rows' => $rows,
            'buckets' => $buckets,
            'reconciliation' => [
                'documents' => $openRemaining,
                'ledger' => $ledger,
                'difference' => bcsub($ledger, $openRemaining, 2),
            ],
            'as_of' => $asOf,
        ];
    }

    /** @param  array<string, mixed>  $row */
    public static function csvRow(array $row): array
    {
        return [
            Csv::cell($row['branch']), Csv::cell($row['customer']), Csv::cell($row['number']), $row['date'],
            $row['total'], $row['paid'], $row['remaining'], $row['status'], $row['days'].' ('.$row['bucket'].')',
            $row['last_payment'] ?? '',
        ];
    }

    private function ledger(int $companyId, ?int $branchId, string $asOf): string
    {
        $nets = app(BalanceSheetService::class)->nets($companyId, $branchId, $asOf);
        $total = '0.00';
        $accounts = Account::where('company_id', $companyId)->where('type', 'asset')->where('module', 'Piutang')->get(['id', 'type', 'is_contra']);
        foreach ($accounts as $account) {
            $total = bcadd($total, LedgerService::normalSigned($nets[$account->id] ?? '0.00', $account->type, (bool) $account->is_contra), 2);
        }

        return $total;
    }
}
