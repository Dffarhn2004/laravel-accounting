<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PayableReportService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PRD 05 #9 Laporan Daftar Hutang: expenses, openings, loans, FIFO leftover, and the undocumented gap.
 */
class PayableReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    private int $otherBranchId;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Hutang Co', 'code' => 'HUT']);
        $this->branchId = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST'])->id;
        $this->otherBranchId = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBR'])->id;
        $this->periodId = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id;
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function opening(string $path, int $branchId, array $items): void
    {
        $this->post($path, [
            'branch_id' => $branchId,
            'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-01-02',
            'items' => $items,
        ])->assertSessionHasNoErrors();
    }

    public function test_documents_fifo_and_the_undocumented_gap_reconcile_to_the_ledger(): void
    {
        $this->opening('/inputan/modal', $this->branchId, [
            ['account_id' => $this->id('2101'), 'amount' => '80000', 'description' => 'Hutang awal'],
        ]);
        $this->opening('/inputan/saldo', $this->otherBranchId, [
            ['account_id' => $this->id('1101'), 'amount' => '50000'],
            ['account_id' => $this->id('2101'), 'amount' => '50000'],
        ]);
        $this->post('/transactions/cash-bank', [
            'branch_id' => $this->branchId,
            'posted_on' => '2026-02-10',
            'transaction_type' => 'terima_pinjaman',
            'from_account_id' => $this->id('2501'),
            'to_account_id' => $this->id('1101'),
            'from_amount' => '60000',
            'to_amount' => '60000',
            'description' => 'Pinjaman bank',
            'idempotency_key' => 'loan-'.Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->post('/accounting/expenses', [
            'branch_id' => $this->branchId,
            'expense_date' => '2026-02-01',
            'payment_method' => 'utang',
            'expense_account_id' => $this->id('5209'),
            'description' => 'Sewa',
            'amount' => '40000',
            'idempotency_key' => 'exp-'.Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->post('/transactions/cash-bank', [
            'branch_id' => $this->branchId,
            'posted_on' => '2026-02-20',
            'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('2101'),
            'from_amount' => '50000',
            'to_amount' => '50000',
            'description' => 'Bayar hutang',
            'idempotency_key' => 'pay-'.Str::uuid(),
        ])->assertSessionHasNoErrors();
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchId,
            'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-02-25',
            'description' => 'Jurnal hutang tanpa dokumen',
            'idempotency_key' => 'ap-gap',
            'source_kind' => 'manual',
            'lines' => [
                ['account_id' => $this->id('1101'), 'debit' => '15000', 'credit' => '0'],
                ['account_id' => $this->id('2101'), 'debit' => '0', 'credit' => '15000'],
            ],
        ]);

        $response = $this->get('/laporan/lap-hutang?as_of=2026-03-01')->assertOk();
        $this->assertSame('accounting/payables', $response->viewData('page')['component']);
        $page = $response->viewData('page')['props'];
        $bySource = collect($page['rows'])->keyBy('source');
        $this->assertSame(['Modal awal', 'Pinjaman', 'Saldo neraca', 'Tanpa dokumen'], $bySource->keys()->sort()->values()->all());
        $this->assertSame('70000.00', $bySource['Modal awal']['remaining']);
        $this->assertSame('10000.00', $bySource['Modal awal']['paid']);
        $this->assertSame('60000.00', $bySource['Pinjaman']['remaining']);
        $this->assertSame('0.00', $bySource['Pinjaman']['paid']);
        $this->assertSame('50000.00', $bySource['Saldo neraca']['remaining']);
        $this->assertSame('15000.00', $bySource['Tanpa dokumen']['remaining']);
        $this->assertSame('180000.00', $page['reconciliation']['documents']);
        $this->assertSame('15000.00', $page['reconciliation']['undocumented']);
        $this->assertSame('195000.00', $page['reconciliation']['ledger']);
        $this->assertSame('0.00', bcsub(
            bcadd($page['reconciliation']['documents'], $page['reconciliation']['undocumented'], 2),
            $page['reconciliation']['ledger'],
            2,
        ));

        $paid = collect($this->get('/laporan/lap-hutang?as_of=2026-03-01&status=paid')->viewData('page')['props']['rows']);
        $this->assertSame(['Beban'], $paid->pluck('source')->all());
        $this->assertSame('Lunas', $paid->first()['status']);
    }

    public function test_another_company_cannot_see_the_debts(): void
    {
        $this->opening('/inputan/modal', $this->branchId, [
            ['account_id' => $this->id('2101'), 'amount' => '80000'],
        ]);
        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $branch = $other->branches()->create(['name' => 'Lain', 'code' => 'L']);
        $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        $this->actingAs(User::factory()->create(['company_id' => $other->id, 'branch_id' => $branch->id]));

        $page = $this->get('/laporan/lap-hutang?as_of=2026-03-01')->assertOk()->viewData('page')['props'];
        $this->assertSame([], $page['rows']);
        $this->assertSame('0.00', $page['reconciliation']['ledger']);
        $this->assertSame([], app(PayableReportService::class)->top($other->id, null, '2026-03-01'));
    }
}
