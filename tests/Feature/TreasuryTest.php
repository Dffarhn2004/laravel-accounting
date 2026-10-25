<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_posts_balanced_journal_within_company(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $cashA = $company->accounts()->create(['code' => '1100', 'name' => 'Bank A', 'type' => 'asset']);
        $cashB = $company->accounts()->create(['code' => '1110', 'name' => 'Bank B', 'type' => 'asset']);
        $a = $company->cashAccounts()->create(['account_id' => $cashA->id, 'name' => 'Bank A', 'type' => 'bank']);
        $b = $company->cashAccounts()->create(['account_id' => $cashB->id, 'name' => 'Bank B', 'type' => 'bank']);

        $transaction = app(TreasuryPostingService::class)->transfer([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'from_cash_account_id' => $a->id, 'to_cash_account_id' => $b->id,
            'from_account_id' => $cashA->id, 'to_account_id' => $cashB->id,
            'amount' => '1000000', 'posted_on' => '2026-01-10', 'idempotency_key' => 'transfer-1',
        ]);

        $this->assertSame('transfer', $transaction->transaction_type);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(0, (float) $transaction->journalEntry->lines->sum('debit') - (float) $transaction->journalEntry->lines->sum('credit'));
    }

    public function test_transfer_rejects_closed_period(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $period->update(['status' => 'closed']);

        $this->expectException(InvalidArgumentException::class);
        app(TreasuryPostingService::class)->transfer([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'from_cash_account_id' => 1, 'to_cash_account_id' => 2,
            'from_account_id' => 1, 'to_account_id' => 2,
            'amount' => '100', 'posted_on' => '2026-01-10', 'idempotency_key' => 'transfer-closed',
        ]);
    }

    private function foundation(): array
    {
        $company = Company::create(['name' => 'Treasury Co', 'code' => 'TREASURY']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $period = $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->actingAs(User::factory()->create());

        return [$company, $branch, $period];
    }
}
