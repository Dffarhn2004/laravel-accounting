<?php

namespace App\Services\Accounting;

use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\TemplateOption;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Pelunasan Piutang" (PRD 04 #4): one row per settlement, 16 columns.
 * Initial payments taken on the invoice itself stay on the invoice register.
 */
class ReceivableRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal Bayar', 'Cabang', 'No Invoice', 'Nama Customer',
        'Keterangan', 'Kode Akun', 'Jenis Pembayaran', 'Jumlah Dibayar', 'Kode Piutang',
        'Nama Akun Piutang', 'Total Tagihan Invoice', 'Sisa Setelah Bayar',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return Receipt::query()
            ->where('company_id', $companyId)
            ->where('receipt_number', 'like', 'PLT-%')
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('received_on', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('receipt_number', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $like))
                    ->orWhereHas('invoice.customer', fn ($customer) => $customer->where('name', 'like', $like))
                    ->orWhereHas('cashAccount', fn ($account) => $account->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('received_on')->orderByDesc('id');
    }

    /** @return array{amount:string, count:int} cash received; reversed settlements are excluded */
    public function totals(Builder $query): array
    {
        $live = (clone $query)->reorder()->whereNull('reversed_at');

        return ['amount' => LedgerService::money((clone $live)->sum('amount')), 'count' => (clone $live)->count()];
    }

    /**
     * @param  Collection<int, Receipt>  $receipts
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $receipts, int $startNo = 1): array
    {
        $receipts->loadMissing([
            'branch:id,name',
            'cashAccount:id,code,name',
            'receivableAccount:id,code,name',
            'invoice:id,invoice_number,customer_id,total',
            'invoice.customer:id,name',
            'attachments:id,attachable_id,attachable_type,original_name,mime',
        ]);
        $labels = TemplateOption::where('group', 'invoice_payment')->whereIn('company_id', $receipts->pluck('company_id')->unique())->pluck('label', 'code');
        $remaining = $this->remainingAfter($receipts);

        $out = [];
        foreach ($receipts->values() as $i => $receipt) {
            $date = $receipt->received_on;
            $out[] = [
                'id' => $receipt->id,
                'status' => $receipt->status,
                'number' => $receipt->receipt_number,
                'invoice_id' => $receipt->invoice_id,
                'attachments' => $receipt->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                'no' => $startNo + $i,
                'month' => $date ? LedgerService::MONTHS[(int) $date->format('n')].' '.$date->format('Y') : '',
                'date' => $date?->toDateString(),
                'branch' => $receipt->branch?->name,
                'invoice' => $receipt->invoice?->invoice_number,
                'customer' => $receipt->invoice?->customer?->name,
                'description' => $receipt->description,
                'cash_code' => $receipt->cashAccount?->code,
                'method' => $labels[$receipt->payment_method] ?? $receipt->payment_method,
                'amount' => LedgerService::money($receipt->amount),
                'receivable_code' => $receipt->receivableAccount?->code,
                'receivable_name' => $receipt->receivableAccount?->name,
                'invoice_total' => LedgerService::money($receipt->invoice?->total ?? '0'),
                'remaining' => $remaining[$receipt->id] ?? '0.00',
                'reversal_reason' => $receipt->reversal_reason,
            ];
        }

        return $out;
    }

    /** One CSV line: the 16 PRD columns (Aksi blank) plus status. */
    public static function csvRow(array $r): array
    {
        return [
            '', count($r['attachments']) ?: '', $r['no'], $r['month'], $r['date'], Csv::cell($r['branch']),
            Csv::cell($r['invoice']), Csv::cell($r['customer']), Csv::cell($r['description']), Csv::cell($r['cash_code']),
            Csv::cell($r['method']), $r['amount'], Csv::cell($r['receivable_code']), Csv::cell($r['receivable_name']),
            $r['invoice_total'], $r['remaining'], $r['status'] === 'reversed' ? 'Dibalik' : 'Aktif',
        ];
    }

    /**
     * Invoice total minus live allocations up to and including this receipt.
     *
     * @param  Collection<int, Receipt>  $receipts
     * @return array<int, string>
     */
    private function remainingAfter(Collection $receipts): array
    {
        $allocations = ReceiptAllocation::query()
            ->whereIn('invoice_id', $receipts->pluck('invoice_id')->filter()->unique())
            ->whereHas('receipt', fn ($q) => $q->whereNull('reversed_at'))
            ->with('receipt:id,received_on')
            ->get()
            ->groupBy('invoice_id');

        $out = [];
        foreach ($receipts as $receipt) {
            $applied = '0.00';
            $date = $receipt->received_on->toDateString();
            foreach ($allocations->get($receipt->invoice_id, []) as $allocation) {
                $otherDate = $allocation->receipt->received_on->toDateString();
                if ($otherDate < $date || ($otherDate === $date && $allocation->receipt_id <= $receipt->id)) {
                    $applied = bcadd($applied, LedgerService::money($allocation->amount), 2);
                }
            }
            $out[$receipt->id] = bcsub(LedgerService::money($receipt->invoice?->total ?? '0'), $applied, 2);
        }

        return $out;
    }
}
