<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\TemplateOption;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Pendapatan" (PRD 04-DATA-TRANSAKSI.md #1): one register row per product line and per
 * "pendapatan lain" of every invoice, 26 columns.
 *
 * Invoice-level amounts (Total Bank/Cash, PPh 23, PPN, Piutang) appear only on the first row of
 * an invoice so that summing a column never double counts. PPh 23 is the estimate from the
 * invoice (ADR-008: it is booked only when the customer withholds at payment).
 */
class RevenueRegisterService
{
    public const PER_PAGE = 25;

    private const MONTHS = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** CSV and on-screen header, in the order the PRD lists them. */
    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'No INV', 'Nama Customer', 'Kode', 'Jenis Pembayaran',
        'Total Bank/Cash', 'Kode', 'PPh 23', 'Kode', 'Beban Discount', 'Kode', 'Kelompok Pendapatan', 'Total Pendapatan',
        'Kode', 'PPN', 'Kode', 'Piutang', 'Produk', 'Qty', 'Harga Satuan', 'Disc %',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return Invoice::query()
            ->where('company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('issued_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('issued_on', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like))
                    ->orWhereHas('lines', fn ($l) => $l->where('description', 'like', $like))
                    ->orWhereHas('otherIncomes', fn ($o) => $o->where('label', 'like', $like)));
            })
            ->orderByDesc('issued_on')->orderByDesc('id');
    }

    /** Number of register rows an invoice produces (lines + other incomes). */
    public function rowCountBefore(Builder $query, int $invoices): int
    {
        if ($invoices <= 0) {
            return 0;
        }

        return (int) (clone $query)->withCount(['lines', 'otherIncomes'])->limit($invoices)->get()
            ->sum(fn ($i) => $i->lines_count + $i->other_incomes_count);
    }

    /** @return array{bank_cash:string, discount:string, revenue:string, vat:string, receivable:string, invoices:int} excludes reversed invoices */
    public function totals(Builder $query): array
    {
        $live = (clone $query)->reorder()->where('status', '!=', 'reversed');

        return [
            'bank_cash' => $this->sum($live, 'paid_amount'),
            'discount' => $this->sum($live, 'discount_total'),
            // Gross revenue = DPP + discount when discount is booked as an expense.
            'revenue' => bcadd($this->sum($live, 'dpp'), $this->sum($live, 'discount_total'), 2),
            'vat' => $this->sum($live, 'vat_amount'),
            'receivable' => bcsub($this->sum($live, 'total'), $this->sum($live, 'paid_amount'), 2),
            'invoices' => (clone $live)->count(),
        ];
    }

    /**
     * Flatten invoices into register rows.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $invoices, int $startNo = 1): array
    {
        $invoices->loadMissing([
            'branch:id,code,name', 'customer:id,code,name', 'lines.revenueAccount:id,code,name', 'otherIncomes.account:id,code,name',
            'paymentAccount:id,code', 'vatOutputAccount:id,code', 'receivableAccount:id,code', 'discountAccount:id,code', 'attachments:id,attachable_id,attachable_type,original_name,mime',
        ]);
        $methods = TemplateOption::where('group', 'invoice_payment')->whereIn('company_id', $invoices->pluck('company_id')->unique())
            ->pluck('label', 'code');

        $no = $startNo;
        $out = [];

        foreach ($invoices as $invoice) {
            $first = true;
            $lines = $invoice->lines->map(fn ($l) => [
                'product' => $l->description,
                'group' => $l->revenueAccount?->name,
                'revenue_code' => $l->revenueAccount?->code,
                'revenue' => bcmul((string) $l->quantity, (string) $l->unit_price, 2),
                'discount' => bcsub(bcmul((string) $l->quantity, (string) $l->unit_price, 2), (string) $l->line_total, 2),
                'qty' => (string) $l->quantity,
                'price' => (string) $l->unit_price,
                'pct' => (string) $l->discount_percent,
            ]);
            // Without a discount account the lines are booked net; the register still shows the line discount.
            $others = $invoice->otherIncomes->map(fn ($o) => [
                'product' => $o->label, 'group' => $o->account?->name, 'revenue_code' => $o->account?->code,
                'revenue' => (string) $o->amount, 'discount' => null, 'qty' => null, 'price' => null, 'pct' => null,
            ]);

            foreach ($lines->concat($others) as $r) {
                $out[] = [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                    'attachments' => $first ? $invoice->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all() : null,
                    'no' => $no++,
                    'month' => self::MONTHS[(int) $invoice->issued_on->format('n')].' '.$invoice->issued_on->format('Y'),
                    'date' => $invoice->issued_on->toDateString(),
                    'branch' => $invoice->branch?->name,
                    'invoice_number' => $invoice->invoice_number,
                    'customer' => $invoice->customer?->name,
                    'customer_code' => $invoice->customer?->code,
                    'payment_method' => $methods[$invoice->payment_method] ?? $invoice->payment_method,
                    'bank_cash' => $first ? (string) $invoice->paid_amount : null,
                    'bank_cash_code' => $first ? $invoice->paymentAccount?->code : null,
                    'pph23' => $first ? (string) $invoice->pph23_amount : null,
                    'pph23_code' => $first && bccomp((string) $invoice->pph23_amount, '0', 2) > 0 ? '1404' : null,
                    'discount' => $r['discount'],
                    'discount_code' => $r['discount'] !== null && bccomp($r['discount'], '0', 2) > 0 ? $invoice->discountAccount?->code : null,
                    'group' => $r['group'],
                    'revenue' => $r['revenue'],
                    'revenue_code' => $r['revenue_code'],
                    'vat' => $first ? (string) $invoice->vat_amount : null,
                    'vat_code' => $first && bccomp((string) $invoice->vat_amount, '0', 2) > 0 ? $invoice->vatOutputAccount?->code : null,
                    'receivable' => $first ? bcsub((string) $invoice->total, (string) $invoice->paid_amount, 2) : null,
                    'receivable_code' => $first ? $invoice->receivableAccount?->code : null,
                    'product' => $r['product'],
                    'qty' => $r['qty'],
                    'price' => $r['price'],
                    'pct' => $r['pct'],
                ];
                $first = false;
            }
        }

        return $out;
    }

    /** One CSV line (26 columns + status). Text cells that a spreadsheet could execute are neutralised. */
    public static function csvRow(array $r): array
    {
        $num = fn ($v) => $v === null ? '' : (string) $v;
        $cell = static fn (?string $v): string => Csv::cell($v);

        return [
            '', count($r['attachments'] ?? []) ?: '', $r['no'], $r['month'], $r['date'], $cell($r['branch']), $cell($r['invoice_number']),
            $cell($r['customer']), $cell($r['customer_code']), $cell($r['payment_method']),
            $num($r['bank_cash']), $num($r['bank_cash_code']), $num($r['pph23']), $num($r['pph23_code']), $num($r['discount']), $num($r['discount_code']),
            $cell($r['group']), $num($r['revenue']), $num($r['revenue_code']), $num($r['vat']), $num($r['vat_code']), $num($r['receivable']),
            $cell($r['product']), $num($r['qty']), $num($r['price']), $num($r['pct']), $r['status'],
        ];
    }

    private function sum(Builder $q, string $column): string
    {
        return bcadd((string) (clone $q)->sum($column), '0', 2);
    }
}
