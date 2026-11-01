<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\OpeningPositionService;
use App\Services\Accounting\ProfitLossService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD 05-LAPORAN.md #2-#5: Laba Rugi (konsolidasi + per cabang), Neraca, Cashflow, on one fixed
 * scenario whose figures are worked out by hand below. The accounting invariants:
 *   - Aktiva = Kewajiban + Ekuitas (per branch and consolidated)
 *   - branch columns add up to the consolidated statement
 *   - cash flow ending cash = balance-sheet cash
 *
 * Scenario (all amounts IDR):
 *   2025-12-15  sale on cash                         Dr 1101 / Cr 4101   1.000.000   (prior-year profit, never closed)
 *   2026-01-01  Saldo Neraca (Pusat): Kas 10jt, Kendaraan 20jt, Akum -5jt | Hutang usaha 3jt, Modal 22jt
 *   2026-03-10  invoice Pusat and 2026-03-12 invoice Cabang B   (rev 260.000, disc 20.000, PPN 26.400, paid 100.000 each)
 *   2026-03-20  overhead 5209 30.000 cash          2026-04-05  depreciation 5303 250.000
 *   2026-05-10  interest income 4901 5.000 bank     2026-05-20  interest expense 5401 2.000 bank
 *   2026-06-10  tax 5501 1.000 cash                 2026-07-01  buy office equipment 1505 4jt cash
 *   2026-08-01  loan 2501 6jt to bank                2026-08-15  prive 3201 500.000 cash
 *   2026-09-01  pay supplier debt 2101 1jt cash
 */
class FinancialStatementsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $pusat;

    private Branch $cabangB;

    private FiscalPeriod $p2026;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Stmt Co', 'code' => 'STM']);
        $this->pusat = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'A1']);
        $this->cabangB = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'B1']);
        $p2025 = $this->company->fiscalPeriods()->create(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $this->p2026 = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'C1']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);

        $this->journal('2025-12-15', '1101', '4101', '1000000', $p2025, 'Penjualan tunai 2025');
        app(OpeningPositionService::class)->postBalanceSheet([
            'company_id' => $this->company->id, 'branch_id' => $this->pusat->id, 'fiscal_period_id' => $this->p2026->id, 'posted_on' => '2026-01-01',
            'items' => [
                ['account_id' => $this->id('1101'), 'amount' => '10000000'], ['account_id' => $this->id('1503'), 'amount' => '20000000'],
                ['account_id' => $this->id('1553'), 'amount' => '5000000'], ['account_id' => $this->id('2101'), 'amount' => '3000000'],
                ['account_id' => $this->id('3101'), 'amount' => '22000000'],
            ],
        ]);
        $this->invoice($this->pusat, '2026-03-10');
        $this->invoice($this->cabangB, '2026-03-12');
        $this->journal('2026-03-20', '5209', '1101', '30000');
        $this->journal('2026-04-05', '5303', '1553', '250000');
        $this->journal('2026-05-10', '1111', '4901', '5000');
        $this->journal('2026-05-20', '5401', '1111', '2000');
        $this->journal('2026-06-10', '5501', '1101', '1000');
        $this->journal('2026-07-01', '1505', '1101', '4000000');
        $this->journal('2026-08-01', '1111', '2501', '6000000');
        $this->journal('2026-08-15', '3201', '1101', '500000');
        $this->journal('2026-09-01', '2101', '1101', '1000000');
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    private function journal(string $date, string $debit, string $credit, string $amount, ?FiscalPeriod $period = null, string $description = 'Jurnal'): void
    {
        static $n = 0;
        $n++;
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => $this->pusat->id, 'fiscal_period_id' => ($period ?? $this->p2026)->id,
            'posted_on' => $date, 'description' => $description, 'idempotency_key' => "stmt-{$n}", 'source_kind' => 'manual',
            'lines' => [['account_id' => $this->id($debit), 'debit' => $amount, 'credit' => '0'], ['account_id' => $this->id($credit), 'debit' => '0', 'credit' => $amount]],
        ]);
    }

    private function invoice(Branch $branch, string $date): void
    {
        static $n = 0;
        $n++;
        app(TransactionPostingService::class)->invoice([
            'company_id' => $this->company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $this->p2026->id,
            'customer_id' => $this->company->customers()->value('id'), 'issued_on' => $date,
            'receivable_account_id' => $this->id('1201'), 'discount_account_id' => $this->id('5103'), 'idempotency_key' => "stmt-inv-{$n}",
            'vat_enabled' => true, 'vat_rate' => '11', 'vat_output_account_id' => $this->id('2301'), 'pph23_rate' => '2',
            'paid_amount' => '100000', 'payment_account_id' => $this->id('1111'),
            'lines' => [
                ['description' => 'Kopi', 'quantity' => '2', 'unit_price' => '100000', 'discount_percent' => '10', 'revenue_account_id' => $this->id('4101')],
                ['description' => 'Konsultasi', 'quantity' => '1', 'unit_price' => '50000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4102')],
            ],
            'other_incomes' => [['label' => 'Ongkir', 'account_id' => $this->id('4104'), 'amount' => '10000']],
        ]);
    }

    private function row(array $rows, string $needle, string $key = 'label'): array
    {
        foreach ($rows as $r) {
            if (($r[$key] ?? null) === $needle) {
                return $r;
            }
        }
        $this->fail("row {$needle} not found");
    }

    // ---- Laba Rugi ----------------------------------------------------------------

    public function test_consolidated_profit_loss_matches_the_hand_worked_figures(): void
    {
        $r = app(ProfitLossService::class)->monthly($this->company->id, 2026, null);
        $total = fn (string $label) => $this->row($r['rows'], $label)['total'];

        $this->assertSame('520000.00', $total('Total Pendapatan Operasional'));
        $this->assertSame('40000.00', $total('Total Direct Cost'));
        $this->assertSame('480000.00', $total('Gross Margin'));
        $this->assertSame('30000.00', $total('Total Overhead'));
        $this->assertSame('450000.00', $total('EBITDA'));
        $this->assertSame('250000.00', $total('Total Depreciation'));
        $this->assertSame('200000.00', $total('EBIT'));
        $this->assertSame('203000.00', $total('EBT (Laba Sebelum Pajak)'));
        $this->assertSame('1000.00', $total('Total Pajak'));
        $this->assertSame('202000.00', $total('Laba Bersih'));
        $this->assertSame('38.84', $this->row($r['rows'], 'Laba Bersih')['ratio'], '% rasio = laba bersih / pendapatan operasional');
        $this->assertSame('202000.00', $r['summary']['net_income']);
    }

    public function test_monthly_columns_put_each_amount_in_the_right_month(): void
    {
        $r = app(ProfitLossService::class)->monthly($this->company->id, 2026, null);
        $revenue = $this->row($r['rows'], 'Total Pendapatan Operasional')['values'];
        $depreciation = $this->row($r['rows'], 'Total Depreciation')['values'];

        $this->assertCount(12, $r['columns']);
        $this->assertSame('Januari', $r['columns'][0]);
        $this->assertSame('520000.00', $revenue[2], 'Maret');
        $this->assertSame('0.00', $revenue[3]);
        $this->assertSame('250000.00', $depreciation[3], 'April');
        $this->assertSame('1000.00', $this->row($r['rows'], 'Total Pajak')['values'][5], 'Juni');
    }

    public function test_account_detail_rows_and_the_prior_year_are_excluded_from_the_year(): void
    {
        $r = app(ProfitLossService::class)->monthly($this->company->id, 2026, null);

        $sales = $this->row($r['rows'], '4101', 'code');
        $this->assertSame('400000.00', $sales['total'], '2 invoices x 200.000 gross; the 2025 sale is not in 2026');
        $this->assertSame('5103', $this->row($r['rows'], '5103', 'code')['code']);

        $hidden = collect($r['rows'])->where('type', 'account')->pluck('code')->all();
        $this->assertNotContains('5201', $hidden, 'accounts without movement are hidden by default');
        $all = app(ProfitLossService::class)->monthly($this->company->id, 2026, null, true);
        $this->assertContains('5201', collect($all['rows'])->where('type', 'account')->pluck('code')->all());

        $y2025 = app(ProfitLossService::class)->monthly($this->company->id, 2025, null);
        $this->assertSame('1000000.00', $this->row($y2025['rows'], 'Laba Bersih')['total']);
    }

    public function test_branch_columns_add_up_to_the_consolidated_statement(): void
    {
        $service = app(ProfitLossService::class);
        $branch = $service->byBranch($this->company->id, '2026-01-01', '2026-12-31', null);
        $consolidated = $service->monthly($this->company->id, 2026, null);

        $this->assertSame(['Pusat', 'Cabang B'], $branch['columns']);
        $this->assertSame([$this->pusat->id, $this->cabangB->id], $branch['branch_ids']);

        foreach ($branch['rows'] as $row) {
            if (! isset($row['values'])) {
                continue;
            }
            $this->assertSame($row['total'], array_reduce($row['values'], fn ($c, $v) => bcadd($c, $v, 2), '0.00'), $row['label']);
            $other = collect($consolidated['rows'])->first(fn ($r) => ($r['label'] ?? null) === $row['label'] && ($r['code'] ?? null) === ($row['code'] ?? null) && isset($r['total']));
            $this->assertSame($other['total'], $row['total'], 'consolidated = sum of branches: '.$row['label']);
        }

        $net = $this->row($branch['rows'], 'Laba Bersih');
        $this->assertSame('240000.00', $net['values'][1], 'Cabang B: 260.000 revenue - 20.000 discount');
        $this->assertSame('-38000.00', $net['values'][0], 'Pusat: its invoice 240.000 less overhead, depreciation, interest and tax');
    }

    // ---- Neraca -------------------------------------------------------------------

    public function test_balance_sheet_balances_and_shows_opening_and_closing_positions(): void
    {
        $r = app(BalanceSheetService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);

        $this->assertTrue($r['balance']['balanced'], 'Aktiva = Pasiva');
        $this->assertSame(['26000000.00', '30754800.00'], $r['balance']['aktiva']);
        $this->assertSame(['26000000.00', '30754800.00'], $r['balance']['pasiva']);
        $this->assertSame(['0.00', '0.00'], $r['balance']['difference']);
        $this->assertSame(['Saldo awal', 'Posisi akhir'], $r['columns']);

        $cash = $this->row($r['rows'], '1101', 'code');
        $this->assertSame(['11000000.00', '5469000.00'], $cash['values']);
        $this->assertSame('-50.28', $cash['growth']);
        $this->assertNull($this->row($r['rows'], '1111', 'code')['growth'], 'growth is N/A when the opening is zero');
        $this->assertSame(['-5000000.00', '-5250000.00'], $this->row($r['rows'], '1553', 'code')['values'], 'contra asset reduces aktiva');
        $this->assertSame(['0.00', '-500000.00'], $this->row($r['rows'], '3201', 'code')['values'], 'prive is a contra equity account: it reduces ekuitas');
    }

    public function test_assets_are_grouped_by_module_not_lumped_into_cash(): void
    {
        $r = app(BalanceSheetService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);
        $sub = fn (string $label) => $this->row($r['rows'], $label)['values'][1];

        $this->assertSame('11672000.00', $sub('Total Kas & Bank'));
        $this->assertSame('332800.00', $sub('Total Piutang'));
        $this->assertSame('18750000.00', $sub('Total Aset Tetap'), '20jt + 4jt - 5,25jt');
        $this->assertSame('8052800.00', $sub('TOTAL KEWAJIBAN'));
    }

    public function test_unclosed_earnings_split_into_prior_years_and_current_year(): void
    {
        $r = app(BalanceSheetService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);

        $this->assertSame(['1000000.00', '1000000.00'], $this->row($r['rows'], 'Laba Ditahan s.d. akhir tahun lalu (belum ditutup)')['values']);
        $this->assertSame(['0.00', '202000.00'], $this->row($r['rows'], 'Laba Tahun Berjalan')['values']);
        $this->assertSame('22702000.00', $this->row($r['rows'], 'TOTAL EKUITAS')['values'][1], '22jt - 500rb prive + 1jt prior + 202rb current');
    }

    public function test_each_branch_balances_on_its_own(): void
    {
        $service = app(BalanceSheetService::class);

        foreach ([$this->pusat, $this->cabangB] as $branch) {
            $r = $service->report($this->company->id, '2026-01-01', '2026-12-31', $branch->id);
            $this->assertTrue($r['balance']['balanced'], $branch->name);
        }

        $b = $service->report($this->company->id, '2026-01-01', '2026-12-31', $this->cabangB->id);
        $this->assertSame('266400.00', $b['balance']['aktiva'][1], 'bank 100.000 + piutang 166.400');
    }

    public function test_balance_sheet_at_an_earlier_date_ignores_later_postings(): void
    {
        $r = app(BalanceSheetService::class)->report($this->company->id, '2026-01-01', '2026-03-31', null);

        $this->assertTrue($r['balance']['balanced']);
        $this->assertNotContains('2501', collect($r['rows'])->pluck('code')->filter()->all(), 'the August loan is not there yet');
    }

    // ---- Cashflow -----------------------------------------------------------------

    public function test_cash_flow_reconciles_to_the_balance_sheet_cash(): void
    {
        $cf = app(CashFlowService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);
        $s = $cf['summary'];

        $this->assertSame('11000000.00', $s['start_cash'], 'opening 10jt + 2025 sale 1jt');
        $this->assertSame('-828000.00', $s['operating']);
        $this->assertSame('-4000000.00', $s['investing']);
        $this->assertSame('5500000.00', $s['financing']);
        $this->assertSame('672000.00', $s['increase']);
        $this->assertSame('11672000.00', $s['end_cash']);
        $this->assertSame('11672000.00', $s['balance_sheet_cash']);
        $this->assertTrue($s['reconciled']);

        $bs = app(BalanceSheetService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);
        $this->assertSame($this->row($bs['rows'], 'Total Kas & Bank')['values'][1], $s['end_cash'], 'ACC-14: ending cash = balance sheet cash');
    }

    public function test_cash_flow_detail_lines_and_the_opening_journal_is_not_a_flow(): void
    {
        $cf = app(CashFlowService::class)->report($this->company->id, '2026-01-01', '2026-12-31', null);
        $labels = collect($cf['rows'])->pluck('label')->all();

        $this->assertContains('Kenaikan Piutang Usaha', $labels);
        $this->assertContains('Perolehan Peralatan Kantor', $labels);
        $this->assertContains('Pinjaman Jangka Pendek', $labels);
        $this->assertContains('Prive / Penarikan Pemilik', $labels);
        $this->assertNotContains('Perolehan Kendaraan', $labels, 'the 20jt vehicle came with the opening balance');
        $this->assertSame('250000.00', $this->row($cf['rows'], 'Beban penyusutan Kendaraan')['values'][0], 'non-cash add-back');
        $this->assertSame('202000.00', $this->row($cf['rows'], 'Laba (rugi) bersih')['values'][1]);
        $this->assertSame('0.00', $this->row($cf['rows'], 'Selisih')['values'][1]);
    }

    public function test_cash_flow_reconciles_for_any_period_and_branch(): void
    {
        $service = app(CashFlowService::class);

        foreach ([['2026-03-01', '2026-03-31', null], ['2026-04-01', '2026-09-30', null], ['2026-01-01', '2026-12-31', $this->cabangB->id], ['2026-05-01', '2026-05-31', $this->pusat->id]] as [$from, $to, $branch]) {
            $s = $service->report($this->company->id, $from, $to, $branch)['summary'];
            $this->assertTrue($s['reconciled'], "{$from}..{$to} branch ".($branch ?? 'all'));
        }
    }

    // ---- Pages --------------------------------------------------------------------

    public function test_report_pages_render_with_filters_and_scope(): void
    {
        $this->get('/reports/profit-loss?year=2026')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/profit-loss')->where('filters.year', 2026)->has('report.rows')->has('branches', 2));
        $this->get('/reports/branch-profit-loss?from=2026-01-01&to=2026-12-31')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/branch-profit-loss')->has('report.columns', 2));
        $this->get('/reports/balance-sheet?to=2026-12-31')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/balance-sheet')->where('report.balance.balanced', true));
        $this->get('/reports/cash-flow?from=2026-01-01&to=2026-12-31')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/cash-flow')->where('report.summary.reconciled', true));
    }

    public function test_filters_are_validated_and_scoped(): void
    {
        $foreign = Company::create(['name' => 'Other', 'code' => 'OTH'])->branches()->create(['name' => 'X', 'code' => 'X']);

        $this->get('/reports/profit-loss?year=1800')->assertSessionHasErrors('year');
        $this->get("/reports/balance-sheet?branch_id={$foreign->id}")->assertSessionHasErrors('branch_id');
        $this->get('/reports/cash-flow?from=2026-05-01&to=2026-04-01')->assertSessionHasErrors('to');
        $this->get('/reports/branch-profit-loss?from=kemarin')->assertSessionHasErrors('from');

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->cabangB->id]));
        $net = collect($this->get('/reports/profit-loss?year=2026&branch_id='.$this->pusat->id)->viewData('page')['props']['report']['rows'])->firstWhere('label', 'Laba Bersih');
        $this->assertSame('240000.00', $net['total'], 'a branch-bound user cannot widen the scope');
        $this->assertCount(1, $this->get('/reports/branch-profit-loss')->viewData('page')['props']['report']['columns']);
    }

    public function test_reports_require_a_company(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/reports/profit-loss', '/reports/branch-profit-loss', '/reports/balance-sheet', '/reports/cash-flow'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
