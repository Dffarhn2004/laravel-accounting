<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FixedAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_depreciates_from_following_month(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $assetAccount = $this->account($company, '1500', 'Fixed Asset', 'asset');
        $cash = $this->account($company, '1100', 'Cash', 'asset');
        $expense = $this->account($company, '6100', 'Depreciation Expense', 'expense');
        $accumulated = $this->account($company, '1590', 'Accumulated Depreciation', 'asset');

        $asset = app(FixedAssetService::class)->acquire([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'name' => 'Equipment', 'asset_number' => 'FA-1', 'acquired_on' => '2026-01-10',
            'cost' => '2400000', 'residual_value' => '0', 'useful_life_months' => 48,
            'asset_account_id' => $assetAccount->id, 'settlement_account_id' => $cash->id,
            'depreciation_expense_account_id' => $expense->id,
            'accumulated_depreciation_account_id' => $accumulated->id,
            'idempotency_key' => 'asset-1',
        ]);

        $posting = app(FixedAssetService::class)->postDepreciation($asset, '2026-02', 'dep-1');

        $this->assertSame('50000.00', (string) $posting->amount);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_same_month_depreciation_is_rejected(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $assetAccount = $this->account($company, '1500', 'Fixed Asset', 'asset');
        $cash = $this->account($company, '1100', 'Cash', 'asset');
        $expense = $this->account($company, '6100', 'Depreciation Expense', 'expense');
        $accumulated = $this->account($company, '1590', 'Accumulated Depreciation', 'asset');
        $asset = app(FixedAssetService::class)->acquire([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'name' => 'Equipment', 'asset_number' => 'FA-2', 'acquired_on' => '2026-01-10',
            'cost' => '100000', 'residual_value' => '0', 'useful_life_months' => 10,
            'asset_account_id' => $assetAccount->id, 'settlement_account_id' => $cash->id,
            'depreciation_expense_account_id' => $expense->id,
            'accumulated_depreciation_account_id' => $accumulated->id,
            'idempotency_key' => 'asset-2',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(FixedAssetService::class)->postDepreciation($asset, '2026-01', 'dep-2');
    }

    private function account(Company $company, string $code, string $name, string $type): Account
    {
        return $company->accounts()->create(compact('code', 'name', 'type'));
    }

    private function foundation(): array
    {
        $company = Company::create(['name' => 'Assets Co', 'code' => 'ASSETS']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $period = $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->actingAs(User::factory()->create());

        return [$company, $branch, $period];
    }
}
