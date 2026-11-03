<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD 05-LAPORAN.md #1 Jurnal Umum and #6 Laporan Buku Besar, with real postings.
 * Invariants: debit = credit, running balances are right across pages, filters and branch scope
 * are honoured server side, nothing leaks across companies.
 */
class GeneralLedgerReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private FiscalPeriod $period;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'GL Co', 'code' => 'GL']);
        $this->branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'CUST-1']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    private function id(string $code, ?Company $company = null): int
    {
        return ($company ?? $this->company)->accounts()->where('code', $code)->value('id');
    }

    /** Reference scenario, 7 journal lines, total 286.400 each side. */
    private function invoice(string $date = '2026-03-10', ?Branch $branch = null): Invoice
    {
        static $n = 0;
        $n++;

        return app(TransactionPostingService::class)->invoice([
            'company_id' => $this->company->id, 'branch_id' => ($branch ?? $this->branch)->id, 'fiscal_period_id' => $this->period->id,
            'customer_id' => $this->company->customers()->value('id'), 'issued_on' => $date,
            'receivable_account_id' => $this->id('1201'), 'discount_account_id' => $this->id('5103'), 'idempotency_key' => "gl-{$n}",
            'vat_enabled' => true, 'vat_rate' => '11', 'vat_output_account_id' => $this->id('2301'), 'pph23_rate' => '2',
            'paid_amount' => '100000', 'payment_account_id' => $this->id('1111'),
            'lines' => [
                ['description' => 'Kopi', 'quantity' => '2', 'unit_price' => '100000', 'discount_percent' => '10', 'revenue_account_id' => $this->id('4101')],
                ['description' => 'Konsultasi', 'quantity' => '1', 'unit_price' => '50000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4102')],
            ],
            'other_incomes' => [['label' => 'Ongkir', 'account_id' => $this->id('4104'), 'amount' => '10000']],
        ]);
    }

    private function manual(string $date, string $debit, string $credit, string $amount, string $description = 'Jurnal', ?Branch $branch = null, ?string $ref = null): JournalEntry
    {
        static $m = 0;
        $m++;

        return app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => ($branch ?? $this->branch)->id, 'fiscal_period_id' => $this->period->id,
            'posted_on' => $date, 'description' => $description, 'idempotency_key' => "man-{$m}-".uniqid(),
            'source_kind' => 'manual', 'reference' => $ref ?? "JM-{$m}",
            'lines' => [['account_id' => $this->id($debit), 'debit' => $amount, 'credit' => '0'], ['account_id' => $this->id($credit), 'debit' => '0', 'credit' => $amount]],
        ]);
    }

    private function props(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('page')['props'];
    }

    // ---- Jurnal Umum --------------------------------------------------------------

    public function test_every_posting_appears_with_source_reference_and_balances(): void
    {
        $invoice = $this->invoice();
        $this->manual('2026-04-01', '1101', '3101', '5000', 'Setor modal', null, 'JM-X');

        $p = $this->props('/accounting/journals');

        $this->assertCount(9, $p['rows'], '7 invoice lines + 2 manual lines');
        $this->assertSame('291400.00', $p['totals']['debit'], '286.400 invoice + 5.000 manual');
        $this->assertSame('291400.00', $p['totals']['credit']);
        $this->assertSame('0.00', $p['totals']['difference'], 'journal always balances');

        $first = $p['rows'][0];
        $this->assertSame('Invoice', $first['source']);
        $this->assertSame($invoice->invoice_number, $first['reference']);
        $this->assertSame('Pusat', $first['branch']);
        $this->assertSame(2026, $first['year']);
        $this->assertSame('Maret', $first['month']);
        $this->assertSame('2026-03-10', $first['date']);
        $this->assertSame("/transactions/invoices/{$invoice->id}", $first['source_url']);

        $manual = collect($p['rows'])->firstWhere('source_kind', 'manual');
        $this->assertSame('JM-X', $manual['reference']);
        $this->assertSame('/data/d-jm?search=JM-X', $manual['source_url']);
    }

    public function test_running_balance_is_on_the_normal_side_and_continues_across_pages(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->invoice('2026-03-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT));   // 8 x 7 = 56 lines -> 2 pages
        }

        $rows = array_merge($this->props('/accounting/journals')['rows'], $this->props('/accounting/journals?page=2')['rows']);
        $this->assertCount(56, $rows);

        $revenue = collect($rows)->where('account_code', '4101')->pluck('balance')->values()->all();
        $this->assertSame(['200000.00', '400000.00', '600000.00', '800000.00', '1000000.00', '1200000.00', '1400000.00', '1600000.00'], $revenue, 'credit-normal revenue grows positive');

        $discount = collect($rows)->where('account_code', '5103')->pluck('balance')->last();
        $this->assertSame('160000.00', $discount, 'debit-normal expense');

        $this->assertSame('2', (string) $this->props('/accounting/journals')['pagination']['last']);
    }

    public function test_filters_by_source_month_branch_and_text(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->invoice('2026-03-10');
        $this->manual('2026-04-05', '1101', '3101', '700', 'Setor April', $other, 'JM-APR');

        $count = fn (string $qs) => count($this->props("/accounting/journals?{$qs}")['rows']);

        $this->assertSame(7, $count('source=invoice'));
        $this->assertSame(2, $count('source=manual'));
        $this->assertSame(2, $count('month=4&year=2026'));
        $this->assertSame(7, $count('month=3'));
        $this->assertSame(2, $count("branch_id={$other->id}"));
        $this->assertSame(2, $count('search=JM-APR'));
        $this->assertSame(2, $count('search=Setor+April'));
        $this->assertSame(1, $count('search=Pendapatan+Lain'), 'matches an account name');
        $this->assertSame(0, $count('source=asset'));

        $this->get('/accounting/journals?source=bogus')->assertSessionHasErrors('source');
        $this->get('/accounting/journals?month=13')->assertSessionHasErrors('month');
    }

    public function test_filtered_balance_still_starts_from_history(): void
    {
        $this->manual('2026-01-05', '1101', '3101', '1000');
        $this->manual('2026-04-05', '1101', '3101', '250');

        $april = collect($this->props('/accounting/journals?month=4')['rows'])->firstWhere('account_code', '1101');

        $this->assertSame('1250.00', $april['balance'], 'balance is not reset by the month filter');
    }

    public function test_branch_bound_user_only_sees_and_balances_their_branch(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->manual('2026-02-01', '1101', '3101', '900', 'Pusat', $this->branch);
        $this->manual('2026-02-02', '1101', '3101', '100', 'Cabang B', $other);

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $p = $this->props('/accounting/journals');

        $this->assertCount(2, $p['rows']);
        $this->assertSame('100.00', collect($p['rows'])->firstWhere('account_code', '1101')['balance'], 'balance of that branch only');
        $this->assertCount(1, $p['branches']);
    }

    public function test_other_company_postings_never_appear(): void
    {
        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $otherBranch = $other->branches()->create(['name' => 'X', 'code' => 'X']);
        $period = $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        app(JournalPostingService::class)->post([
            'company_id' => $other->id, 'branch_id' => $otherBranch->id, 'fiscal_period_id' => $period->id, 'posted_on' => '2026-02-01',
            'description' => 'Rahasia', 'idempotency_key' => 'secret', 'source_kind' => 'manual',
            'lines' => [['account_id' => $this->id('1101', $other), 'debit' => '1', 'credit' => '0'], ['account_id' => $this->id('3101', $other), 'debit' => '0', 'credit' => '1']],
        ]);
        $this->manual('2026-02-01', '1101', '3101', '5');

        $this->assertCount(2, $this->props('/accounting/journals')['rows']);
        $this->assertCount(0, $this->props('/accounting/journals?search=Rahasia')['rows']);
    }

    public function test_reversal_is_listed_and_the_journal_still_balances(): void
    {
        $invoice = $this->invoice();
        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2026-03-12'])->assertSessionHasNoErrors();

        $p = $this->props('/accounting/journals');

        $this->assertCount(14, $p['rows']);
        $this->assertSame('0.00', $p['totals']['difference']);
        $this->assertCount(7, $this->props('/accounting/journals?source=reversal')['rows']);
        $this->assertSame($invoice->invoice_number, collect($p['rows'])->firstWhere('source_kind', 'reversal')['reference']);
        // the AR account is back to zero after the mirror entry
        $this->assertSame('0.00', collect($p['rows'])->where('account_code', '1201')->last()['balance']);
    }

    public function test_journal_csv_export_follows_filters_and_neutralises_formulas(): void
    {
        $this->manual('2026-02-01', '1101', '3101', '10', '=SUM(A1)');
        $this->invoice();

        $csv = $this->get('/accounting/journals/export?source=manual')->assertOk()->streamedContent();
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($csv, 3))));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(3, $lines, 'header + 2 manual lines');
        $this->assertSame(['Sumber', 'No Trx', 'Cabang', 'Tahun', 'Bulan', 'Tanggal', 'No Akun', 'Nama Akun', 'Keterangan', 'Debit', 'Kredit', 'Saldo'], str_getcsv($lines[0]));
        $this->assertStringContainsString("'=SUM(A1)", $csv);
        $this->assertSame('10.00', str_getcsv($lines[1])[11], 'running balance in the export');
    }

    public function test_pages_require_a_company(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/accounting/journals')->assertForbidden();
        $this->get('/accounting/ledger')->assertForbidden();
    }

    // ---- Buku Besar ---------------------------------------------------------------

    public function test_summary_lists_all_accounts_with_opening_period_movement_and_closing(): void
    {
        $this->invoice('2026-03-10');
        $this->manual('2026-05-01', '5209', '1101', '300');

        $p = $this->props('/accounting/ledger?from=2026-04-01&to=2026-12-31');
        $row = fn (string $code) => collect($p['summary'])->firstWhere('code', $code);

        $this->assertGreaterThanOrEqual(70, count($p['summary']), 'all accounts, even without postings');
        $this->assertSame(['opening' => '200000.00', 'debit' => '0.00', 'credit' => '0.00', 'closing' => '200000.00'], collect($row('4101'))->only(['opening', 'debit', 'credit', 'closing'])->all());
        $this->assertSame('20000.00', $row('5103')['opening'], 'debit-normal expense is positive');
        $this->assertSame(['opening' => '0.00', 'debit' => '300.00', 'credit' => '0.00', 'closing' => '300.00'], collect($row('5209'))->only(['opening', 'debit', 'credit', 'closing'])->all());
        $this->assertSame(['opening' => '0.00', 'debit' => '0.00', 'credit' => '0.00', 'closing' => '0.00'], collect($row('1404'))->only(['opening', 'debit', 'credit', 'closing'])->all());
        $this->assertSame($p['summaryTotals']['debit'], $p['summaryTotals']['credit'], 'the books balance');
    }

    public function test_period_end_excludes_later_postings(): void
    {
        $this->manual('2026-03-01', '5209', '1101', '100');
        $this->manual('2026-09-01', '5209', '1101', '900');

        $closing = collect($this->props('/accounting/ledger?to=2026-06-30')['summary'])->firstWhere('code', '5209')['closing'];

        $this->assertSame('100.00', $closing);
    }

    public function test_detail_shows_counter_accounts_running_balance_and_normal_side(): void
    {
        $this->invoice('2026-03-10');
        $this->manual('2026-03-20', '1111', '1101', '40000', 'Setor bank');

        $p = $this->props('/accounting/ledger?account_id='.$this->id('1111'));
        $d = $p['detail'];

        $this->assertSame('1111', $d['account']['code']);
        $this->assertSame('Debit', $d['account']['normal']);
        $this->assertSame('0.00', $d['opening']);
        $this->assertCount(2, $d['rows']);
        $this->assertSame(['100000.00', '140000.00'], array_column($d['rows'], 'balance'));
        $this->assertSame('1101', $d['rows'][1]['counter']);
        $this->assertSame('Invoice', $d['rows'][0]['source']);
        $this->assertStringContainsString('4101', $d['rows'][0]['counter']);
        $this->assertSame('140000.00', $d['totals']['debit']);

        // credit-normal account reads positive
        $rev = $this->props('/accounting/ledger?account_id='.$this->id('4101'))['detail'];
        $this->assertSame('Kredit', $rev['account']['normal']);
        $this->assertSame('200000.00', $rev['rows'][0]['balance']);
    }

    public function test_detail_opening_balance_comes_from_before_the_period(): void
    {
        $this->manual('2026-01-10', '1101', '3101', '1000');
        $this->manual('2026-03-10', '1101', '3101', '500');

        $d = $this->props('/accounting/ledger?account_id='.$this->id('1101').'&from=2026-02-01')['detail'];

        $this->assertSame('1000.00', $d['opening']);
        $this->assertSame(['1500.00'], array_column($d['rows'], 'balance'));
    }

    public function test_detail_balance_is_correct_on_the_second_page(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->manual('2026-02-01', '1101', '3101', '1000');
        }

        $second = $this->props('/accounting/ledger?account_id='.$this->id('1101').'&page=2')['detail'];

        $this->assertCount(1, $second['rows']);
        $this->assertSame('101000.00', $second['rows'][0]['balance']);
        $this->assertSame(2, $second['pagination']['last']);
    }

    public function test_ledger_validation_and_isolation(): void
    {
        $foreign = Company::create(['name' => 'Other', 'code' => 'OTH'])->accounts()->create(['code' => '1101', 'name' => 'Kas', 'type' => 'asset']);

        $this->get('/accounting/ledger?account_id='.$foreign->id)->assertSessionHasErrors('account_id');
        $this->get('/accounting/ledger?from=2026-05-01&to=2026-04-01')->assertSessionHasErrors('to');
        $this->get('/accounting/ledger?from=kemarin')->assertSessionHasErrors('from');
    }

    public function test_ledger_csv_exports(): void
    {
        $this->invoice();
        $account = $this->id('1111');

        $summary = $this->get('/accounting/ledger/export?type=summary')->assertOk()->streamedContent();
        $this->assertStringContainsString('Kode,Nama,"Saldo Awal",Debit,Kredit,"Saldo Akhir"', $summary);
        $this->assertStringContainsString('1111,"Bank Utama",0.00,100000.00,0.00,100000.00', $summary);

        $detail = $this->get("/accounting/ledger/export?type=detail&account_id={$account}")->assertOk()->streamedContent();
        $this->assertStringContainsString('Tanggal,"No Trx",Sumber,Keterangan,"Akun Lawan",Debit,Kredit,Saldo', $detail);
        $this->assertStringContainsString('100000.00', $detail);

        $this->get('/accounting/ledger/export?type=detail')->assertStatus(422);
    }
}
