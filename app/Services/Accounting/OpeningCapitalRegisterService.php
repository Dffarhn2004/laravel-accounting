<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\OpeningBalance;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Data Modal Awal" (PRD 04 #8): one row per asset or liability item.
 * The owner's capital plug is the other side of each row, not a row of its own.
 * Accumulated depreciation on a fixed asset becomes its own credit row.
 */
class OpeningCapitalRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal Cut-off', 'Cabang', 'Akun', 'Keterangan', 'Nilai', 'Posisi',
        'Kode Debit', 'Nama Debit', 'Nilai Debit', 'Kode Kredit', 'Nama Kredit', 'Nilai Kredit',
    ];

    /** @param array{search?:?string, branch_id?:?int, from?:?string, to?:?string} $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return OpeningBalance::query()
            ->where('company_id', $companyId)
            ->where('method', OpeningPositionService::MODAL_AWAL)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('posted_on', '<=', $d))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('description', 'like', $like)
                    ->orWhereHas('lines', fn ($l) => $l->where('description', 'like', $like))
                    ->orWhereHas('lines.account', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('posted_on')->orderByDesc('id');
    }

    /** @return array{debit:string, credit:string, count:int} */
    public function totals(Builder $query): array
    {
        $openings = (clone $query)->reorder()->whereNull('reversed_at')->get();
        $debit = '0.00';
        $credit = '0.00';
        foreach ($this->rows($openings) as $row) {
            $debit = bcadd($debit, $row['debit'], 2);
            $credit = bcadd($credit, $row['credit'], 2);
        }

        return ['debit' => $debit, 'credit' => $credit, 'count' => $openings->count()];
    }

    /**
     * @param  Collection<int, OpeningBalance>  $openings
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $openings, int $startNo = 1): array
    {
        $openings->loadMissing(['branch:id,name', 'lines.account:id,code,name', 'attachments:id,attachable_id,attachable_type,original_name,mime']);
        $companyIds = $openings->pluck('company_id')->unique();
        $capital = Account::whereIn('company_id', $companyIds)->where('code', '3102')->get()->keyBy('company_id');
        $categories = AssetCategory::whereIn('company_id', $companyIds)->with('accumulatedAccount:id,code,name')->get()->keyBy('asset_account_id');

        $out = [];
        $no = $startNo;
        foreach ($openings as $opening) {
            $plug = $capital->get($opening->company_id);
            foreach ($opening->lines as $line) {
                if ($line->description === 'Modal awal pemilik' || $line->account?->code === '3102') {
                    continue;
                }
                $out[] = $this->present($opening, $line->account, LedgerService::money($line->amount), $line->side, $line->description, $plug, $no++);
                $accumulated = LedgerService::money($line->meta['accumulated'] ?? '0');
                $accumAccount = $categories->get($line->account_id)?->accumulatedAccount;
                if ($accumAccount && bccomp($accumulated, '0', 2) > 0) {
                    $out[] = $this->present($opening, $accumAccount, $accumulated, 'credit', 'Akumulasi penyusutan', $plug, $no++);
                }
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $r */
    public static function csvRow(array $r): array
    {
        return [
            '', count($r['attachments']) ?: '', $r['no'], $r['month'], $r['date'], Csv::cell($r['branch']),
            Csv::cell($r['account']), Csv::cell($r['description']), $r['amount'], $r['position'],
            Csv::cell($r['debit_code']), Csv::cell($r['debit_name']), $r['debit'],
            Csv::cell($r['credit_code']), Csv::cell($r['credit_name']), $r['credit'],
            $r['status'] === 'reversed' ? 'Dibalik' : 'Aktif',
        ];
    }

    /** @return array<string, mixed> */
    private function present(OpeningBalance $opening, ?Account $account, string $amount, string $side, ?string $description, ?Account $plug, int $no): array
    {
        $debitAccount = $side === 'debit' ? $account : $plug;
        $creditAccount = $side === 'debit' ? $plug : $account;

        return [
            'id' => $opening->id,
            'status' => $opening->status,
            'attachments' => $opening->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
            'no' => $no,
            'month' => LedgerService::MONTHS[(int) $opening->posted_on->format('n')].' '.$opening->posted_on->format('Y'),
            'date' => $opening->posted_on->toDateString(),
            'branch' => $opening->branch?->name,
            'account' => $account?->name,
            'description' => $description,
            'amount' => $amount,
            'position' => $side === 'debit' ? 'Debit' : 'Kredit',
            'debit_code' => $debitAccount?->code,
            'debit_name' => $debitAccount?->name,
            'debit' => $amount,
            'credit_code' => $creditAccount?->code,
            'credit_name' => $creditAccount?->name,
            'credit' => $amount,
        ];
    }
}
