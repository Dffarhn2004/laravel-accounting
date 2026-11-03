<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Jurnal Manual" (PRD 04 #9): one row per journal line, 12 columns.
 * Balance is checked on the journal itself, not by summing a calendar date.
 */
class ManualJournalRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Akun', 'Keterangan', 'Debit', 'Kredit', 'Kode Akun', 'Cek Balance',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('source_kind', 'manual')
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('lines.account', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('lines', fn ($l) => $l->where('description', 'like', $like)));
            })
            ->orderByDesc('posted_on')->orderByDesc('id');
    }

    /** @return array{debit:string, credit:string, count:int} */
    public function totals(Builder $query): array
    {
        $ids = (clone $query)->reorder()->where('status', '!=', 'reversed')->pluck('id');
        $sums = JournalLine::whereIn('journal_entry_id', $ids)->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')->first();

        return [
            'debit' => LedgerService::money($sums->debit ?? 0),
            'credit' => LedgerService::money($sums->credit ?? 0),
            'count' => $ids->count(),
        ];
    }

    /**
     * @param  Collection<int, JournalEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $entries, int $startNo = 1): array
    {
        $entries->loadMissing(['branch:id,name', 'lines.account:id,code,name', 'attachments:id,attachable_id,attachable_type,original_name,mime']);
        $out = [];
        $no = $startNo;
        foreach ($entries as $entry) {
            $debit = '0.00';
            $credit = '0.00';
            foreach ($entry->lines as $line) {
                $debit = bcadd($debit, LedgerService::money($line->debit), 2);
                $credit = bcadd($credit, LedgerService::money($line->credit), 2);
            }
            $balanced = bccomp($debit, $credit, 2) === 0 ? 'Seimbang' : 'Tidak seimbang';
            foreach ($entry->lines as $line) {
                $out[] = [
                    'id' => $entry->id,
                    'line_id' => $line->id,
                    'status' => $entry->status,
                    'number' => $entry->reference,
                    'attachments' => $entry->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                    'no' => $no++,
                    'month' => LedgerService::MONTHS[(int) $entry->posted_on->format('n')].' '.$entry->posted_on->format('Y'),
                    'date' => $entry->posted_on->toDateString(),
                    'branch' => $entry->branch?->name,
                    'account' => $line->account?->name,
                    'description' => $line->description ?: $entry->description,
                    'debit' => LedgerService::money($line->debit),
                    'credit' => LedgerService::money($line->credit),
                    'code' => $line->account?->code,
                    'balance' => $balanced,
                ];
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $r */
    public static function csvRow(array $r): array
    {
        return [
            '', count($r['attachments']) ?: '', $r['no'], $r['month'], $r['date'], Csv::cell($r['branch']),
            Csv::cell($r['account']), Csv::cell($r['description']), $r['debit'], $r['credit'], Csv::cell($r['code']),
            $r['balance'], $r['status'] === 'reversed' ? 'Dibalik' : 'Aktif',
        ];
    }
}
