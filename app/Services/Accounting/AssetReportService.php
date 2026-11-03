<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\DepreciationPosting;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Laporan Daftar Asset (PRD 05 #11) plus the general-ledger control. */
class AssetReportService
{
    /**
     * @param  array{as_of:string, account_id:?int, source:?string, status:?string}  $filters
     * @return array<string, mixed>
     */
    public function build(int $companyId, ?int $branchId, array $filters): array
    {
        $assets = FixedAsset::with(['branch:id,name', 'assetAccount:id,code,name,type,is_contra', 'accumulatedAccount:id,code,name,type,is_contra', 'schedule'])
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('acquired_on', '<=', $filters['as_of'])
            ->when($filters['account_id'], fn ($q, $id) => $q->where('asset_account_id', $id))
            ->when($filters['source'], fn ($q, $source) => $q->where('source', $source))
            ->when($filters['status'] === 'active', fn ($q) => $q->whereNull('reversed_at'))
            ->when($filters['status'] === 'reversed', fn ($q) => $q->whereNotNull('reversed_at'))
            ->orderBy('code')->get();
        $through = Carbon::parse($filters['as_of'])->format('Y-m');
        $posted = DepreciationPosting::whereIn('fixed_asset_id', $assets->pluck('id'))->whereNull('reversed_at')
            ->where('period_month', '<=', $through)
            ->selectRaw('fixed_asset_id, COUNT(*) as months, SUM(amount) as posted')
            ->groupBy('fixed_asset_id')->get()->keyBy('fixed_asset_id');

        $rows = [];
        $groups = [];
        foreach ($assets as $asset) {
            $stat = $posted->get($asset->id);
            $livePosted = bcadd((string) ($stat->posted ?? '0'), '0', 2);
            $allLive = bcadd((string) DepreciationPosting::where('fixed_asset_id', $asset->id)->whereNull('reversed_at')->sum('amount'), '0', 2);
            $cutoff = bcsub((string) ($asset->schedule?->accumulated_amount ?? '0'), $allLive, 2);
            $accumulated = $asset->reversed_at ? '0.00' : bcadd($cutoff, $livePosted, 2);
            $service = app(FixedAssetService::class);
            $start = $asset->useful_life_months > 0 ? $service->firstMonth($asset) : '';
            $end = '';
            if ($start !== '') {
                $end = Carbon::createFromFormat('Y-m', $start)->addMonths($asset->useful_life_months - 1)->format('Y-m');
            }
            $row = [
                'branch' => $asset->branch?->name,
                'date' => $asset->acquired_on->toDateString(),
                'source' => $asset->source === 'opening' ? 'Modal' : 'Beli',
                'code' => $asset->code,
                'account' => trim(($asset->assetAccount?->code ?? '').' — '.($asset->assetAccount?->name ?? '')),
                'account_id' => $asset->asset_account_id,
                'cost' => (string) $asset->cost,
                'life_years' => (string) intdiv((int) $asset->useful_life_months, 12),
                'monthly' => (string) ($asset->schedule?->amount ?? '0.00'),
                'start' => $start,
                'end' => $end,
                'months' => (int) ($stat->months ?? 0),
                'accumulated' => $accumulated,
                'book' => $asset->reversed_at ? '0.00' : bcsub((string) $asset->cost, $accumulated, 2),
                'status' => $asset->reversed_at ? 'Dibalik' : 'Aktif',
            ];
            $rows[] = $row;
            if ($asset->reversed_at) {
                continue;
            }
            $key = $asset->asset_account_id;
            $groups[$key] ??= ['account' => $row['account'], 'cost' => '0.00', 'accumulated' => '0.00', 'book' => '0.00', 'count' => 0];
            $groups[$key]['cost'] = bcadd($groups[$key]['cost'], $row['cost'], 2);
            $groups[$key]['accumulated'] = bcadd($groups[$key]['accumulated'], $row['accumulated'], 2);
            $groups[$key]['book'] = bcadd($groups[$key]['book'], $row['book'], 2);
            $groups[$key]['count']++;
        }

        return [
            'rows' => $rows,
            'groups' => array_values($groups),
            'control' => $this->control($companyId, $branchId, $filters),
        ];
    }

    /** @param  array{as_of:string, account_id:?int}  $filters */
    private function control(int $companyId, ?int $branchId, array $filters): array
    {
        $active = FixedAsset::with(['assetAccount:id,code,name,type,is_contra', 'accumulatedAccount:id,code,name,type,is_contra', 'schedule'])
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereNull('reversed_at')
            ->whereDate('acquired_on', '<=', $filters['as_of'])
            ->when($filters['account_id'], fn ($q, $id) => $q->where('asset_account_id', $id))
            ->get();
        $nets = app(BalanceSheetService::class)->nets($companyId, $branchId, $filters['as_of']);
        $through = Carbon::parse($filters['as_of'])->format('Y-m');

        $costRows = $this->accountControl($active, $nets, 'asset', function (FixedAsset $asset) {
            return (string) $asset->cost;
        });
        $accumRows = $this->accountControl($active->filter(fn ($a) => $a->accumulated_depreciation_account_id), $nets, 'accumulated', function (FixedAsset $asset) use ($through) {
            $postedThrough = bcadd((string) DepreciationPosting::where('fixed_asset_id', $asset->id)->whereNull('reversed_at')->where('period_month', '<=', $through)->sum('amount'), '0', 2);
            $postedAll = bcadd((string) DepreciationPosting::where('fixed_asset_id', $asset->id)->whereNull('reversed_at')->sum('amount'), '0', 2);
            $cutoff = bcsub((string) ($asset->schedule?->accumulated_amount ?? '0'), $postedAll, 2);

            return bcadd($cutoff, $postedThrough, 2);
        });

        return ['cost' => $costRows, 'accumulated' => $accumRows];
    }

    /**
     * @param  Collection<int, FixedAsset>  $assets
     * @param  array<int, string>  $nets
     * @return array<int, array<string, string>>
     */
    private function accountControl(Collection $assets, array $nets, string $side, callable $amount): array
    {
        $buckets = [];
        foreach ($assets as $asset) {
            $account = $side === 'asset' ? $asset->assetAccount : $asset->accumulatedAccount;
            if (! $account) {
                continue;
            }
            $buckets[$account->id]['account'] = $account;
            $buckets[$account->id]['register'] = bcadd($buckets[$account->id]['register'] ?? '0.00', $amount($asset), 2);
        }
        $rows = [];
        foreach ($buckets as $id => $bucket) {
            /** @var Account $account */
            $account = $bucket['account'];
            $ledger = LedgerService::normalSigned($nets[$id] ?? '0.00', $account->type, (bool) $account->is_contra);
            $rows[] = [
                'account' => $account->code.' — '.$account->name,
                'register' => $bucket['register'],
                'ledger' => $ledger,
                'difference' => bcsub($ledger, $bucket['register'], 2),
            ];
        }

        return $rows;
    }
}
