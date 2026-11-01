<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\User;
use App\Services\Accounting\OpeningPositionService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRD 07-DASHBOARD.md: six KPIs from the ledger (cash is cash only), period and branch filters
 * applied server side, eight integrity controls, and no invented numbers for what does not exist yet.
 *
 * Scenario: opening Kas 10jt / Modal 10jt (Pusat); invoice 2026-03-10 (revenue 260.000, discount 20.000,
 * PPN 26.400, paid 100.000 -> AR 166.400); expense on credit 50.000 (overhead, hutang usaha).
 *   cash 10.100.000 | assets 10.266.400 | AR 166.400 | AP 50.000 | revenue 260.000 | net income 190.000
 */
class DashboardReportingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $pusat;

    private Branch $cabangB;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Dash Co', 'code' => 'DSH']);
        $this->pusat = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'A1']);
        $this->cabangB = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'B1']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'C1']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    private function seedBooks(array $openingExtra = []): void
    {
        app(OpeningPositionService::class)->postBalanceSheet([
            'company_id' => $this->company->id, 'branch_id' => $this->pusat->id, 'fiscal_period_id' => $this->period->id, 'posted_on' => '2026-01-01',
            'items' => array_merge([['account_id' => $this->id('1101'), 'amount' => '10000000'], ['account_id' => $this->id('3101'), 'amount' => '10000000']], $openingExtra),
        ]);
        $this->invoice($this->pusat, '2026-03-10');
        app(TransactionPostingService::class)->expense([
            'company_id' => $this->company->id, 'branch_id' => $this->pusat->id, 'fiscal_period_id' => $this->period->id, 'reference' => 'EXP-1',
            'expense_account_id' => $this->id('5209'), 'payable_account_id' => $this->id('2101'), 'expense_date' => '2026-03-20', 'amount' => '50000', 'idempotency_key' => 'dash-exp',
        ]);
    }

    private function invoice(Branch $branch, string $date): void
    {
        static $n = 0;
        $n++;
        app(TransactionPostingService::class)->invoice([
            'company_id' => $this->company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $this->period->id,
            'customer_id' => $this->company->customers()->value('id'), 'issued_on' => $date,
            'receivable_account_id' => $this->id('1201'), 'discount_account_id' => $this->id('5103'), 'idempotency_key' => "dash-inv-{$n}",
            'vat_enabled' => true, 'vat_rate' => '11', 'vat_output_account_id' => $this->id('2301'), 'pph23_rate' => '0',
            'paid_amount' => '100000', 'payment_account_id' => $this->id('1111'),
            'lines' => [
                ['description' => 'Kopi', 'quantity' => '2', 'unit_price' => '100000', 'discount_percent' => '10', 'revenue_account_id' => $this->id('4101')],
                ['description' => 'Konsultasi', 'quantity' => '1', 'unit_price' => '50000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4102')],
            ],
            'other_incomes' => [['label' => 'Ongkir', 'account_id' => $this->id('4104'), 'amount' => '10000']],
        ]);
    }

    private function dash(string $qs = 'from=2026-01-01&to=2026-12-31'): array
    {
        return $this->get("/dashboard?{$qs}")->assertOk()->viewData('page')['props'];
    }

    private function control(array $dashboard, string $key): array
    {
        return collect($dashboard['integrity']['controls'])->firstWhere('key', $key);
    }

    // ---- KPIs ---------------------------------------------------------------------

    public function test_six_kpis_come_from_the_ledger_and_cash_is_not_total_assets(): void
    {
        $this->seedBooks();
        $k = $this->dash()['dashboard']['kpis'];

        $this->assertSame('260000.00', $k['revenue']);
        $this->assertSame('190000.00', $k['net_income']);
        $this->assertSame('10100000.00', $k['cash'], 'cash and bank accounts only');
        $this->assertSame('10266400.00', $k['total_assets']);
        $this->assertSame('166400.00', $k['receivable']);
        $this->assertSame('50000.00', $k['payable']);
        $this->assertNotSame($k['cash'], $k['total_assets']);
    }

    public function test_period_filter_moves_flows_and_balances_correctly(): void
    {
        $this->seedBooks();

        $before = $this->dash('from=2026-01-01&to=2026-03-01')['dashboard']['kpis'];
        $this->assertSame('0.00', $before['revenue']);
        $this->assertSame('10000000.00', $before['cash']);
        $this->assertSame('0.00', $before['receivable']);
        $this->assertSame([], $this->dash('from=2026-01-01&to=2026-03-01')['dashboard']['top_receivables'], 'invoices issued after the period end are not open yet');

        $afterIssue = $this->dash('from=2026-04-01&to=2026-12-31')['dashboard']['kpis'];
        $this->assertSame('0.00', $afterIssue['revenue'], 'March revenue is outside April-December');
        $this->assertSame('166400.00', $afterIssue['receivable'], 'but the receivable is still open at the end');
    }

    public function test_branch_filter_scopes_every_figure_and_a_bound_user_cannot_widen_it(): void
    {
        $this->seedBooks();
        $this->invoice($this->cabangB, '2026-03-12');

        $all = $this->dash()['dashboard']['kpis'];
        $b = $this->dash("from=2026-01-01&to=2026-12-31&branch_id={$this->cabangB->id}")['dashboard']['kpis'];

        $this->assertSame('520000.00', $all['revenue']);
        $this->assertSame('260000.00', $b['revenue']);
        $this->assertSame('100000.00', $b['cash']);
        $this->assertSame('0.00', $b['payable'], 'the expense belongs to Pusat');

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->cabangB->id]));
        $bound = $this->dash("from=2026-01-01&to=2026-12-31&branch_id={$this->pusat->id}");
        $this->assertSame('260000.00', $bound['dashboard']['kpis']['revenue']);
        $this->assertCount(1, $bound['branches']);
        $this->assertCount(1, $bound['dashboard']['branches']);
    }

    public function test_filter_validation(): void
    {
        $foreign = Company::create(['name' => 'Other', 'code' => 'OTH'])->branches()->create(['name' => 'X', 'code' => 'X']);

        $this->get("/dashboard?branch_id={$foreign->id}")->assertSessionHasErrors('branch_id');
        $this->get('/dashboard?from=2026-05-01&to=2026-04-01')->assertSessionHasErrors('to');
        $this->get('/dashboard?from=kemarin')->assertSessionHasErrors('from');
    }

    // ---- Analytics ----------------------------------------------------------------

    public function test_trends_compositions_branches_matrix_and_top_five(): void
    {
        $this->seedBooks();
        $this->invoice($this->cabangB, '2026-03-12');
        $d = $this->dash()['dashboard'];

        $this->assertCount(12, $d['trend']['months']);
        $this->assertSame('520000.00', $d['trend']['revenue'][2], 'March');
        $this->assertSame('0.00', $d['trend']['revenue'][3]);
        $this->assertSame('90000.00', $d['trend']['expense'][2], 'discounts 40.000 + overhead 50.000');
        $this->assertSame('430000.00', $d['trend']['profit'][2]);

        $this->assertSame('10000000.00', $d['trend']['cash'][0], 'the opening balance is the carried-in cash');
        $this->assertSame('10200000.00', $d['trend']['cash'][2]);
        $this->assertSame('10200000.00', $d['trend']['cash'][11]);

        $compositions = collect($d['expense_composition'])->pluck('amount', 'label')->all();
        $this->assertSame('40000.00', $compositions['Direct Cost']);
        $this->assertSame('50000.00', $compositions['Overhead']);
        $assets = collect($d['asset_composition'])->pluck('amount', 'label')->all();
        $this->assertSame('10200000.00', $assets['Kas & Bank']);
        $this->assertSame('332800.00', $assets['Piutang']);

        $this->assertSame(['Pusat', 'Cabang B'], array_column($d['branches'], 'name'));
        $this->assertSame(['190000.00', '240000.00'], array_column($d['branches'], 'net_income'));

        $this->assertSame('Laba Bersih', end($d['matrix'])['label']);
        $this->assertCount(12, end($d['matrix'])['values']);

        $this->assertCount(2, $d['top_receivables']);
        $this->assertSame('166400.00', $d['top_receivables'][0]['amount']);
        $this->assertSame('PT Pembeli', $d['top_receivables'][0]['party']);
        $this->assertSame([['id' => $d['top_payables'][0]['id'], 'number' => 'EXP-1', 'party' => null, 'date' => '2026-03-20', 'amount' => '50000.00']], $d['top_payables']);
        $this->assertSame('200000.00', $d['cash_flow']['increase'], 'cash grew by the two invoice payments; the opening is the starting point, not a flow');
    }

    public function test_things_that_do_not_exist_yet_are_not_faked(): void
    {
        $this->seedBooks();
        $page = $this->dash();

        $this->assertArrayNotHasKey('health', $page, 'no score from journal balance');
        $this->assertSame(['health', 'ratios', 'budget', 'dividend'], array_column($page['dashboard']['unavailable'], 'key'));
        $this->assertArrayNotHasKey('score', $page['dashboard']);
    }

    // ---- Integrity controls -------------------------------------------------------

    public function test_a_consistent_book_is_valid_with_eight_passing_controls(): void
    {
        $this->seedBooks();
        $i = $this->dash()['dashboard']['integrity'];

        $this->assertCount(8, $i['controls']);
        $this->assertSame(array_fill(0, 8, 'ok'), array_column($i['controls'], 'status'));
        $this->assertSame('valid', $i['status']);
        $this->assertSame(['journal', 'balance_sheet', 'branches', 'cash_flow', 'earnings', 'receivable', 'payable', 'assets'], array_column($i['controls'], 'key'));
    }

    public function test_opening_receivable_without_invoices_is_a_warning_not_a_failure(): void
    {
        $this->seedBooks([['account_id' => $this->id('1201'), 'amount' => '500000'], ['account_id' => $this->id('3301'), 'amount' => '500000']]);
        $i = $this->dash()['dashboard']['integrity'];

        $this->assertSame('warn', $this->control($this->dash()['dashboard'], 'receivable')['status']);
        $this->assertStringContainsString('saldo awal', $this->control($this->dash()['dashboard'], 'receivable')['detail']);
        $this->assertSame('attention', $i['status']);
        $this->assertStringStartsWith('PERHATIAN', $i['summary']);
    }

    public function test_a_corrupt_journal_makes_the_dashboard_invalid(): void
    {
        $this->seedBooks();
        // Simulate corruption that bypasses the posting service: one-sided journal.
        $entryId = DB::table('journal_entries')->insertGetId([
            'company_id' => $this->company->id, 'branch_id' => $this->pusat->id, 'fiscal_period_id' => $this->period->id, 'posted_on' => '2026-05-01',
            'description' => 'rusak', 'idempotency_key' => 'corrupt', 'status' => 'posted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_lines')->insert(['journal_entry_id' => $entryId, 'account_id' => $this->id('1101'), 'debit' => 123, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $d = $this->dash()['dashboard'];

        $this->assertSame('fail', $this->control($d, 'journal')['status']);
        $this->assertStringContainsString('1 jurnal tidak seimbang', $this->control($d, 'journal')['detail']);
        $this->assertSame('fail', $this->control($d, 'balance_sheet')['status']);
        $this->assertSame('invalid', $d['integrity']['status']);
        $this->assertStringStartsWith('TIDAK VALID', $d['integrity']['summary']);
    }

    public function test_an_empty_company_says_so_instead_of_claiming_valid(): void
    {
        $fresh = Company::create(['name' => 'Baru', 'code' => 'NEW']);
        $this->actingAs(User::factory()->create(['company_id' => $fresh->id]));

        $d = $this->dash()['dashboard'];

        $this->assertSame('empty', $d['integrity']['status']);
        $this->assertSame('0.00', $d['kpis']['cash']);
        $this->assertSame([], $d['top_receivables']);
    }

    public function test_user_without_a_company_gets_an_empty_dashboard_not_an_error(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p->component('dashboard')->where('dashboard', null));
    }
}
