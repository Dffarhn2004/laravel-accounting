<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_journal_is_posted_once_when_retried(): void
    {
        [$company, $branch, $period, $cash, $equity] = $this->fixture();
        $payload = [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'fiscal_period_id' => $period->id,
            'posted_on' => '2026-01-01',
            'description' => 'Opening capital',
            'idempotency_key' => 'opening-1',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '100000.00', 'credit' => '0.00'],
                ['account_id' => $equity->id, 'debit' => '0.00', 'credit' => '100000.00'],
            ],
        ];

        $service = app(JournalPostingService::class);
        $first = $service->post($payload);
        $second = $service->post($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_unbalanced_journal_is_rejected_before_persisting(): void
    {
        [$company, $branch, $period, $cash, $equity] = $this->fixture();

        $this->expectException(InvalidArgumentException::class);
        app(JournalPostingService::class)->post([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'fiscal_period_id' => $period->id,
            'posted_on' => '2026-01-01',
            'description' => 'Invalid',
            'idempotency_key' => 'invalid-1',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_id' => $equity->id, 'debit' => '0.00', 'credit' => '99.00'],
            ],
        ]);
    }

    private function fixture(): array
    {
        $company = Company::create(['name' => 'Test Co', 'code' => 'TEST']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $period = FiscalPeriod::create([
            'company_id' => $company->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $equity = Account::create(['company_id' => $company->id, 'code' => '3100', 'name' => 'Equity', 'type' => 'equity']);

        return [$company, $branch, $period, $cash, $equity];
    }
}
