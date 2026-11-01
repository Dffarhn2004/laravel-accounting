<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\TemplateOption;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Beban" (PRD 04-DATA-TRANSAKSI.md #2): one row per expense, 14 columns. The counter
 * account is the cash/bank account (paid) or the payable account (open); its value equals the
 * expense total, so the register ties to the journal.
 */
class ExpenseRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Jenis Pembayaran', 'Kode Akun', 'Akun Beban',
        'Keterangan', 'Total Transaksi', 'Kode Lawan', 'Nama Akun Lawan', 'Nilai Lawan',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return Expense::query()
            ->where('company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('expenseAccount', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('settlementAccount', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('expense_date')->orderByDesc('id');
    }

    /** @return array{amount:string, expenses:int} excludes reversed expenses */
    public function totals(Builder $query): array
    {
        $live = (clone $query)->reorder()->where('status', '!=', 'reversed');

        return ['amount' => bcadd((string) (clone $live)->sum('amount'), '0', 2), 'expenses' => (clone $live)->count()];
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $expenses, int $startNo = 1): array
    {
        $expenses->loadMissing(['branch:id,name', 'expenseAccount:id,code,name', 'settlementAccount:id,code,name', 'attachments:id,attachable_id,attachable_type,original_name,mime']);
        $methods = TemplateOption::where('group', 'outflow_payment')->whereIn('company_id', $expenses->pluck('company_id')->unique())->pluck('label', 'code');

        $out = [];
        foreach ($expenses->values() as $i => $e) {
            $out[] = [
                'expense_id' => $e->id,
                'status' => $e->status,
                'reference' => $e->reference,
                'attachments' => $e->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                'no' => $startNo + $i,
                'month' => LedgerService::MONTHS[(int) $e->expense_date->format('n')].' '.$e->expense_date->format('Y'),
                'date' => $e->expense_date->toDateString(),
                'branch' => $e->branch?->name,
                'payment_method' => $methods[$e->payment_method] ?? $e->payment_method,
                'account_code' => $e->expenseAccount?->code,
                'account_name' => $e->expenseAccount?->name,
                'description' => $e->description,
                'amount' => (string) $e->amount,
                'counter_code' => $e->settlementAccount?->code,
                'counter_name' => $e->settlementAccount?->name,
                'counter_amount' => (string) $e->amount,
            ];
        }

        return $out;
    }

    public function rowCountBefore(Builder $query, int $expenses): int
    {
        return max(0, $expenses);
    }

    /** One CSV line: the 14 PRD columns (Aksi blank) plus status. */
    public static function csvRow(array $r): array
    {
        return [
            '', count($r['attachments']) ?: '', $r['no'], $r['month'], $r['date'], Csv::cell($r['branch']), Csv::cell($r['payment_method']),
            Csv::cell($r['account_code']), Csv::cell($r['account_name']), Csv::cell($r['description']), $r['amount'],
            Csv::cell($r['counter_code']), Csv::cell($r['counter_name']), $r['counter_amount'], $r['status'],
        ];
    }
}
