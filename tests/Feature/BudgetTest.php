<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\User;
use App\Services\Accounting\BudgetService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_achievement_is_calculated(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $account = $company->accounts()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'income']);
        $budget = app(BudgetService::class)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'fiscal_year' => 2026,
            'account_id' => $account->id,
            'activity' => 'sales',
            'monthly_lines' => [['month' => 1, 'amount' => '100000000']],
        ]);
        app(JournalPostingService::class)->post([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'fiscal_period_id' => $period->id,
            'posted_on' => '2026-01-15',
            'description' => 'Actual sales',
            'idempotency_key' => 'budget-actual-1',
            'lines' => [
                ['account_id' => $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset'])->id, 'debit' => '80000000', 'credit' => '0'],
                ['account_id' => $account->id, 'debit' => '0', 'credit' => '80000000'],
            ],
        ]);

        $this->assertSame('draft', $budget->status);
        $this->assertSame('80.00', app(\App\Services\Accounting\BudgetReportService::class)
            ->achievement($budget)['achievement']);
    }

    public function test_zero_budget_achievement_is_not_division_by_zero(): void
    {
        [$company, $branch] = $this->foundation();
        $account = $company->accounts()->create(['code' => '5000', 'name' => 'Expense', 'type' => 'expense']);
        $budget = app(BudgetService::class)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'fiscal_year' => 2026,
            'account_id' => $account->id,
            'monthly_lines' => [['month' => 1, 'amount' => '0']],
        ]);

        $this->assertNull(app(\App\Services\Accounting\BudgetReportService::class)->achievement($budget)['achievement']);
    }

    private function foundation(): array
    {
        $company = Company::create(['name' => 'Budget Co', 'code' => 'BUDGET']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $period = $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->actingAs(User::factory()->create());

        return [$company, $branch, $period];
    }
}
