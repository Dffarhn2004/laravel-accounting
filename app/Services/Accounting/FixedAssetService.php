<?php

namespace App\Services\Accounting;

use App\Models\DepreciationPosting;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FixedAssetService
{
    public function reverseAcquisition(FixedAsset $asset, array $data): FixedAsset
    {
        app(FixedAssetReversalService::class)->reverseAcquisition($asset, $data);

        return $asset->fresh();
    }

    public function acquire(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            if ((float) $data['cost'] <= 0 || (float) $data['residual_value'] < 0) {
                throw new InvalidArgumentException('Asset values are invalid.');
            }
            if ((float) $data['residual_value'] > (float) $data['cost']) {
                throw new InvalidArgumentException('Residual value cannot exceed cost.');
            }
            if (FixedAsset::where('company_id', $data['company_id'])
                ->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Duplicate asset acquisition.');
            }

            $asset = FixedAsset::create([
                ...$data,
                'code' => $data['asset_number'] ?? $data['code'] ?? 'FA-'.$data['idempotency_key'],
                'status' => 'active',
            ]);
            $schedule = DepreciationSchedule::create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'fixed_asset_id' => $asset->id,
                'period_month' => 1,
                'period_on' => $asset->acquired_on,
                'amount' => $this->monthlyAmount($asset),
                'accumulated_amount' => '0.00',
                'book_value' => $asset->cost,
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['acquired_on'],
                'description' => $data['description'] ?? 'Fixed asset acquisition',
                'source_type' => self::class,
                'source_id' => (string) $asset->id,
                'lines' => [
                    ['account_id' => $data['asset_account_id'], 'debit' => $data['cost'], 'credit' => '0'],
                    ['account_id' => $data['settlement_account_id'], 'debit' => '0', 'credit' => $data['cost']],
                ],
            ]);
            $entry = JournalEntry::query()
                ->where('source_type', self::class)
                ->where('source_id', (string) $asset->id)
                ->latest('id')
                ->first();
            return $asset->load('schedule')->setRelation('journalEntry', $entry);
        });
    }

    public function postDepreciation(FixedAsset $asset, string $month, string $idempotencyKey): DepreciationPosting
    {
        return DB::transaction(function () use ($asset, $month, $idempotencyKey) {
            if ($asset->useful_life_months <= 0) {
                throw new InvalidArgumentException('Asset is not depreciable.');
            }
            $period = FiscalPeriod::where('company_id', $asset->company_id)
                ->where('starts_on', '<=', $month.'-01')
                ->where('ends_on', '>=', $month.'-01')->first();
            if (! $period) {
                throw new InvalidArgumentException('Depreciation period is not configured.');
            }
            if ($period->status !== 'open') {
                throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
            }
            $acquisitionMonth = substr((string) $asset->acquired_on, 0, 7);
            if ($month <= $acquisitionMonth) {
                throw new InvalidArgumentException('Depreciation starts the month after acquisition.');
            }
            if (DepreciationPosting::where('fixed_asset_id', $asset->id)->where('period_month', $month)->exists()) {
                throw new InvalidArgumentException('Depreciation month already posted.');
            }
            $schedule = $asset->schedule()->lockForUpdate()->firstOrFail();
            $amount = min((float) $schedule->amount, (float) $asset->cost - (float) $asset->residual_value - (float) $schedule->accumulated_amount);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Asset is fully depreciated.');
            }
            $posting = DepreciationPosting::create([
                'company_id' => $asset->company_id, 'branch_id' => $asset->branch_id,
                'fiscal_period_id' => $period->id, 'fixed_asset_id' => $asset->id,
                'depreciation_schedule_id' => $schedule->id,
                'period_month' => $month,
                'posted_on' => $month.'-01',
                'amount' => number_format($amount, 2, '.', ''),
                'idempotency_key' => $idempotencyKey,
            ]);
            app(JournalPostingService::class)->post([
                'company_id' => $asset->company_id, 'branch_id' => $asset->branch_id,
                'fiscal_period_id' => $period->id, 'posted_on' => $month.'-01',
                'description' => 'Depreciation: '.$asset->name, 'idempotency_key' => $idempotencyKey,
                'source_type' => self::class, 'source_id' => (string) $posting->id,
                'lines' => [
                    ['account_id' => $asset->depreciation_expense_account_id, 'debit' => $posting->amount, 'credit' => '0'],
                    ['account_id' => $asset->accumulated_depreciation_account_id, 'debit' => '0', 'credit' => $posting->amount],
                ],
            ]);
            $schedule->update([
                'accumulated_amount' => bcadd((string) $schedule->accumulated_amount, (string) $posting->amount, 2),
                'book_value' => bcsub((string) $schedule->book_value, (string) $posting->amount, 2),
            ]);
            return $posting->load('journalEntry');
        });
    }

    private function monthlyAmount(FixedAsset $asset): string
    {
        if ($asset->useful_life_months <= 0) return '0.00';
        return bcdiv(bcsub((string) $asset->cost, (string) $asset->residual_value, 2), (string) $asset->useful_life_months, 2);
    }

    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
    }
}
