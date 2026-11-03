<?php

namespace App\Services\Accounting;

use App\Models\DepreciationPosting;
use App\Models\FixedAsset;
use App\Models\TemplateOption;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Data Asset, 17 columns from PRD 04 #5. */
class AssetRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Jenis Pembayaran', 'Kode Akun', 'Akun Asset',
        'Keterangan', 'Total Transaksi', 'Kode Lawan', 'Nama Akun Lawan', 'Nilai Lawan', 'Umur (thn)', 'Penyusutan/Bulan', 'Tahun Berakhir',
    ];

    /** @param  array{search:?string, branch_id:?int}  $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch): Builder
    {
        return FixedAsset::query()
            ->where('company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'], fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['search'], function ($q, $search) {
                $like = '%'.$search.'%';
                $q->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->orderByDesc('acquired_on')->orderByDesc('id');
    }

    /**
     * @param  Collection<int, FixedAsset>  $assets
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $assets, int $startNo): array
    {
        $assets->load([
            'branch:id,name', 'assetAccount:id,code,name', 'settlementAccount:id,code,name', 'schedule',
            'attachments:id,attachable_id,attachable_type,original_name,mime',
        ]);
        $methods = TemplateOption::where('group', 'outflow_payment')->whereIn('company_id', $assets->pluck('company_id')->unique())->pluck('label', 'code');
        $methods['modal'] = 'Modal';
        $live = DepreciationPosting::whereIn('fixed_asset_id', $assets->pluck('id'))->whereNull('reversed_at')
            ->selectRaw('fixed_asset_id, SUM(amount) as posted')->groupBy('fixed_asset_id')->pluck('posted', 'fixed_asset_id');

        $out = [];
        foreach ($assets->values() as $i => $asset) {
            $posted = bcadd((string) ($live[$asset->id] ?? '0'), '0', 2);
            $cutoff = bcsub((string) ($asset->schedule?->accumulated_amount ?? '0'), $posted, 2);
            $years = intdiv((int) $asset->useful_life_months, 12);
            $out[] = [
                'asset_id' => $asset->id,
                'reversed' => $asset->reversed_at !== null,
                'can_reverse_depreciation' => $asset->reversed_at === null && bccomp($posted, '0', 2) > 0,
                'can_reverse_purchase' => $asset->reversed_at === null && $asset->source === 'purchase' && bccomp($posted, '0', 2) === 0,
                'reference' => $asset->code,
                'attachments' => $asset->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                'no' => $startNo + $i,
                'month' => LedgerService::MONTHS[(int) $asset->acquired_on->format('n')].' '.$asset->acquired_on->format('Y'),
                'date' => $asset->acquired_on->toDateString(),
                'branch' => $asset->branch?->name,
                'payment' => $methods[$asset->payment_method] ?? $asset->payment_method,
                'account_code' => $asset->assetAccount?->code,
                'account_name' => $asset->assetAccount?->name,
                'description' => $asset->description ?: $asset->name,
                'total' => (string) $asset->cost,
                'counter_code' => $asset->settlementAccount?->code,
                'counter_name' => $asset->settlementAccount?->name,
                'counter_amount' => bcsub((string) $asset->cost, $cutoff, 2),
                'life_years' => (string) $years,
                'monthly' => (string) ($asset->schedule?->amount ?? '0.00'),
                'end_year' => $years > 0 ? (string) ((int) $asset->acquired_on->format('Y') + $years) : '',
            ];
        }

        return $out;
    }

    /** @param  Builder<FixedAsset>  $query */
    public function totals(Builder $query): array
    {
        $active = (clone $query)->whereNull('reversed_at');

        return [
            'count' => (clone $active)->count(),
            'amount' => bcadd((string) (clone $active)->sum('cost'), '0', 2),
        ];
    }

    /** @param  array<string, mixed>  $row */
    public static function csvRow(array $row): array
    {
        return [
            '', count($row['attachments']) ?: '', $row['no'], $row['month'], $row['date'], Csv::cell($row['branch']), Csv::cell($row['payment']),
            Csv::cell($row['account_code']), Csv::cell($row['account_name']), Csv::cell($row['description']), $row['total'],
            Csv::cell($row['counter_code']), Csv::cell($row['counter_name']), $row['counter_amount'], $row['life_years'], $row['monthly'], $row['end_year'],
            $row['reversed'] ? 'Dibalik' : 'Aktif',
        ];
    }
}
