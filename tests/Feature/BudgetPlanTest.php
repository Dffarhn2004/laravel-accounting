<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\BudgetPlanService;
use App\Services\Accounting\JournalPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD 06 Anggaran: Qty x Tarif input, register, rekap shaped as Laba Rugi, and Kinerja vs the journal (ACC-18).
 */
class BudgetPlanTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    private int $otherBranchId;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Anggaran Co', 'code' => 'ANG']);
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

    /** @param  array<string, mixed>  $over */
    private function payload(array $over = []): array
    {
        return $over + [
            'branch_id' => $this->branchId,
            'fiscal_year' => 2026,
            'account_id' => $this->id('4101'),
            'activity' => 'Penjualan paket',
            'unit' => 'paket',
            'rate' => '10000000',
            'quantities' => ['1', '2', '3', '4', '0', '0', '0', '0', '0', '0', '0', '0'],
        ];
    }

    private function journal(string $debit, string $credit, string $amount, string $date, string $key, ?int $branch = null): void
    {
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => $branch ?? $this->branchId, 'fiscal_period_id' => $this->periodId,
            'posted_on' => $date, 'description' => $key, 'idempotency_key' => $key, 'source_kind' => 'manual',
            'lines' => [
                ['account_id' => $this->id($debit), 'debit' => $amount, 'credit' => '0'],
                ['account_id' => $this->id($credit), 'debit' => '0', 'credit' => $amount],
            ],
        ]);
    }

    public function test_each_month_is_quantity_times_rate_and_both_are_stored(): void
    {
        $this->post('/accounting/budgets', $this->payload())->assertSessionHasNoErrors()->assertRedirect('/anggaran/ang-data?year=2026');

        $budget = Budget::firstOrFail();
        $this->assertSame('published', $budget->status);
        $this->assertSame('10000000.00', $budget->rate);
        $mar = $budget->lines()->where('month', 3)->first();
        $this->assertSame('3.00', $mar->quantity);
        $this->assertSame('30000000.00', $mar->amount);

        $row = $this->get('/anggaran/ang-data?year=2026')->assertOk()->viewData('page')['props']['rows'][0];
        $this->assertSame('100000000.00', $row['total']);
        $this->assertSame('10.00', $row['total_qty']);
        $this->assertSame('paket', $row['unit']);
    }

    public function test_only_profit_and_loss_accounts_of_the_company_are_accepted_and_rows_are_unique(): void
    {
        $this->post('/accounting/budgets', $this->payload(['account_id' => $this->id('1101')]))->assertSessionHasErrors('account_id');
        $this->post('/accounting/budgets', $this->payload(['quantities' => ['1']]))->assertSessionHasErrors('quantities');
        $this->post('/accounting/budgets', $this->payload(['rate' => '-1']))->assertSessionHasErrors('rate');

        $foreign = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $foreignBranch = $foreign->branches()->create(['name' => 'X', 'code' => 'X'])->id;
        $this->post('/accounting/budgets', $this->payload(['branch_id' => $foreignBranch]))->assertSessionHasErrors('branch_id');

        $this->post('/accounting/budgets', $this->payload())->assertSessionHasNoErrors();
        $this->post('/accounting/budgets', $this->payload())->assertSessionHasErrors('activity');
        $this->assertSame(1, Budget::count());
        $this->post('/accounting/budgets', $this->payload(['branch_id' => $this->otherBranchId]))->assertSessionHasNoErrors();
        $this->assertSame(2, Budget::count());
    }

    public function test_edit_and_delete_stay_inside_the_company(): void
    {
        $this->post('/accounting/budgets', $this->payload())->assertSessionHasNoErrors();
        $budget = Budget::firstOrFail();

        $this->patch("/accounting/budgets/{$budget->id}", $this->payload(['rate' => '5000000', 'activity' => 'Penjualan paket B']))->assertSessionHasNoErrors();
        $this->assertSame('20000000.00', $budget->lines()->where('month', 4)->value('amount'));
        $this->assertSame(1, Budget::count());

        $page = $this->get("/accounting/budgets?edit={$budget->id}")->assertOk()->viewData('page')['props'];
        $this->assertSame('Penjualan paket B', $page['editing']['activity']);

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $other->branches()->create(['name' => 'X', 'code' => 'X']);
        $this->actingAs(User::factory()->create(['company_id' => $other->id]));
        $this->delete("/accounting/budgets/{$budget->id}")->assertNotFound();
        $this->get("/accounting/budgets?edit={$budget->id}")->assertNotFound();
        $this->assertSame(1, Budget::count());

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
        $this->delete("/accounting/budgets/{$budget->id}")->assertRedirect();
        $this->assertSame(0, Budget::count());
        $this->assertDatabaseCount('budget_monthly_lines', 0);
    }

    public function test_recap_uses_the_profit_and_loss_structure_and_filters_by_branch(): void
    {
        $this->post('/accounting/budgets', $this->payload())->assertSessionHasNoErrors();
        $this->post('/accounting/budgets', $this->payload(['account_id' => $this->id('5209'), 'activity' => 'Sewa', 'unit' => 'bulan', 'rate' => '2000000', 'quantities' => array_fill(0, 12, '1')]))->assertSessionHasNoErrors();
        $this->post('/accounting/budgets', $this->payload(['branch_id' => $this->otherBranchId, 'rate' => '1000000']))->assertSessionHasNoErrors();

        $all = $this->get('/anggaran/ang-rekap?year=2026')->assertOk()->viewData('page')['props']['report'];
        $this->assertSame('110000000.00', $all['summary']['revenue']);
        $this->assertSame('24000000.00', $all['summary']['expense']);
        $this->assertSame('86000000.00', $all['summary']['net_income']);
        $this->assertSame('78.18', $all['summary']['net_margin']);
        $this->assertSame('Total Pendapatan Operasional', collect($all['rows'])->firstWhere('type', 'subtotal')['label']);

        $branch = $this->get("/anggaran/ang-rekap?year=2026&branch_id={$this->otherBranchId}")->viewData('page')['props']['report'];
        $this->assertSame('10000000.00', $branch['summary']['revenue']);
        $this->assertSame('10000000.00', $branch['summary']['net_income']);
    }

    public function test_kinerja_compares_the_plan_with_the_journal_acc18(): void
    {
        $this->post('/accounting/budgets', $this->payload(['quantities' => ['10', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0']]))->assertSessionHasNoErrors();
        $this->post('/accounting/budgets', $this->payload(['account_id' => $this->id('5209'), 'activity' => 'Sewa', 'rate' => '1000000', 'quantities' => ['10', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0']]))->assertSessionHasNoErrors();
        $this->journal('1101', '4101', '80000000', '2026-01-15', 'aktual-jual');
        $this->journal('5209', '1101', '12000000', '2026-01-20', 'aktual-sewa');
        $this->journal('5204', '1101', '500000', '2026-03-05', 'beban-tanpa-anggaran');

        $report = $this->get('/anggaran/ang-kinerja?from=2026-01-01&to=2026-01-31')->assertOk()->viewData('page')['props']['report'];
        $rows = collect($report['rows'])->keyBy('code');
        $this->assertSame('100000000.00', $rows['4101']['budget']);
        $this->assertSame('80000000.00', $rows['4101']['actual']);
        $this->assertSame('80.00', $rows['4101']['achievement']);
        $this->assertSame('bad', $rows['4101']['status']);
        $this->assertSame('120.00', $rows['5209']['achievement']);
        $this->assertSame('bad', $rows['5209']['status']);
        $this->assertSame('80.00', $report['kpi']['income']['achievement']);
        $this->assertSame('68000000.00', $report['kpi']['profit']['actual']);

        // An expense without a target is N/A, not a division by zero; it only shows when it has actuals in range.
        $wide = collect($this->get('/anggaran/ang-kinerja?from=2026-01-01&to=2026-03-31')->viewData('page')['props']['report']['rows']);
        $noTarget = $wide->firstWhere('actual', '500000.00');
        $this->assertNull($noTarget['achievement']);
        $this->assertSame('na', $noTarget['status']);
        $this->assertNull(collect($report['rows'])->firstWhere('actual', '500000.00'));
    }

    public function test_achievement_classes(): void
    {
        $svc = app(BudgetPlanService::class);
        $this->assertSame('good', $svc->achievement('100', '100', false)['status']);
        $this->assertSame('near', $svc->achievement('100', '95', false)['status']);
        $this->assertSame('bad', $svc->achievement('100', '50', false)['status']);
        $this->assertSame('good', $svc->achievement('100', '100', true)['status']);
        $this->assertSame('near', $svc->achievement('100', '108', true)['status']);
        $this->assertSame('bad', $svc->achievement('100', '150', true)['status']);
        $this->assertSame(['achievement' => null, 'status' => 'na'], $svc->achievement('0.00', '10', false));
    }

    public function test_export_is_csv(): void
    {
        $this->post('/accounting/budgets', $this->payload())->assertSessionHasNoErrors();
        $this->get('/anggaran/ang-data/export?year=2026')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
