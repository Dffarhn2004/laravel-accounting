<?php

namespace App\Services\Accounting;

use App\Models\TemplateOption;
use App\Models\TreasuryTransaction;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Kas & Bank" (PRD 04 #7): one row per movement, 14 columns. Debit (Ke) equals credit (Dari).
 */
class CashBankRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Jenis Transaksi', 'Keterangan',
        'Kode Ke (Debit)', 'Nama Akun Ke', 'Nilai Debit', 'Kode Dari (Kredit)', 'Nama Akun Dari', 'Nilai Kredit',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return TreasuryTransaction::query()
            ->where('company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) use ($companyId) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $codes = TemplateOption::where('company_id', $companyId)->where('group', 'treasury_type')->where('label', 'like', $like)->pluck('code');
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereIn('transaction_type', $codes)
                    ->orWhereHas('fromAccount', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('toAccount', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('posted_on')->orderByDesc('id');
    }

    /** @return array{amount:string, count:int} excludes reversed movements; debit equals credit */
    public function totals(Builder $query): array
    {
        $live = (clone $query)->reorder()->where('status', '!=', 'reversed');

        return ['amount' => bcadd((string) (clone $live)->sum('amount'), '0', 2), 'count' => (clone $live)->count()];
    }

    /**
     * @param  Collection<int, TreasuryTransaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $transactions, int $startNo = 1): array
    {
        $transactions->loadMissing(['branch:id,name', 'fromAccount:id,code,name', 'toAccount:id,code,name', 'attachments:id,attachable_id,attachable_type,original_name,mime']);
        $labels = TemplateOption::where('group', 'treasury_type')->whereIn('company_id', $transactions->pluck('company_id')->unique())->pluck('label', 'code');

        $out = [];
        foreach ($transactions->values() as $i => $t) {
            $date = $t->posted_on;
            $out[] = [
                'id' => $t->id,
                'status' => $t->status,
                'reference' => $t->reference,
                'attachments' => $t->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                'no' => $startNo + $i,
                'month' => $date ? LedgerService::MONTHS[(int) $date->format('n')].' '.$date->format('Y') : '',
                'date' => $date?->toDateString(),
                'branch' => $t->branch?->name,
                'type' => $labels[$t->transaction_type] ?? $t->transaction_type,
                'description' => $t->description,
                'to_code' => $t->toAccount?->code,
                'to_name' => $t->toAccount?->name,
                'debit' => (string) $t->amount,
                'from_code' => $t->fromAccount?->code,
                'from_name' => $t->fromAccount?->name,
                'credit' => (string) $t->amount,
            ];
        }

        return $out;
    }

    /** One CSV line: the 14 PRD columns (Aksi blank) plus status. */
    public static function csvRow(array $r): array
    {
        return [
            '', count($r['attachments']) ?: '', $r['no'], $r['month'], $r['date'], Csv::cell($r['branch']), Csv::cell($r['type']),
            Csv::cell($r['description']), Csv::cell($r['to_code']), Csv::cell($r['to_name']), $r['debit'],
            Csv::cell($r['from_code']), Csv::cell($r['from_name']), $r['credit'], $r['status'],
        ];
    }
}
