<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD 04-DATA-TRANSAKSI.md #1 Data Pendapatan: 26-column register, search/branch filter,
 * evidence, CSV export, and correction by reversal (never edit/delete a posted journal).
 */
class RevenueRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private User $user;

    private Customer $customer;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Reg Co', 'code' => 'REG']);
        $this->branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->customer = $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'CUST-1']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
        Storage::fake('local');
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    /** The reference scenario: 2 product lines (10% discount on the first), 1 other income, PPN 11%, 100.000 paid. */
    private function invoice(array $override = []): Invoice
    {
        static $n = 0;
        $n++;

        return app(TransactionPostingService::class)->invoice(array_replace([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'fiscal_period_id' => $this->period->id,
            'customer_id' => $this->customer->id, 'issued_on' => '2026-03-10',
            'receivable_account_id' => $this->id('1201'), 'discount_account_id' => $this->id('5103'),
            'idempotency_key' => "reg-{$n}-".uniqid(),
            'vat_enabled' => true, 'vat_rate' => '11', 'vat_output_account_id' => $this->id('2301'), 'pph23_rate' => '2',
            'payment_method' => 'bank', 'paid_amount' => '100000', 'payment_account_id' => $this->id('1111'),
            'lines' => [
                ['description' => 'Kopi', 'quantity' => '2', 'unit_price' => '100000', 'discount_percent' => '10', 'revenue_account_id' => $this->id('4101')],
                ['description' => 'Konsultasi', 'quantity' => '1', 'unit_price' => '50000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4102')],
            ],
            'other_incomes' => [['label' => 'Ongkir', 'account_id' => $this->id('4104'), 'amount' => '10000']],
        ], $override));
    }

    /** A one-line, unpaid, untaxed invoice. */
    private function simple(string $key, array $override = [], string $price = '1000', string $description = 'Barang'): Invoice
    {
        return $this->invoice($override + [
            'idempotency_key' => $key, 'paid_amount' => '0', 'payment_account_id' => null, 'other_incomes' => [], 'vat_enabled' => false,
            'lines' => [['description' => $description, 'quantity' => '1', 'unit_price' => $price, 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')]],
        ]);
    }

    // ---- Register content ---------------------------------------------------------

    public function test_register_has_one_row_per_line_and_other_income_with_the_26_prd_columns(): void
    {
        $this->invoice();

        $this->get('/transactions/invoices')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/data-pendapatan')
            ->has('columns', 26)
            ->has('rows', 3)
            // first row: product line + the invoice-level amounts
            ->where('rows.0.no', 1)
            ->where('rows.0.month', 'Maret 2026')
            ->where('rows.0.branch', 'Pusat')
            ->where('rows.0.invoice_number', 'INV-2026-00001')
            ->where('rows.0.customer', 'PT Pembeli')->where('rows.0.customer_code', 'CUST-1')
            ->where('rows.0.payment_method', 'Bank')
            ->where('rows.0.bank_cash', '100000.00')->where('rows.0.bank_cash_code', '1111')
            ->where('rows.0.pph23', '4800.00')->where('rows.0.pph23_code', '1404')
            ->where('rows.0.discount', '20000.00')->where('rows.0.discount_code', '5103')
            ->where('rows.0.group', 'Penjualan Produk')->where('rows.0.revenue', '200000.00')->where('rows.0.revenue_code', '4101')
            ->where('rows.0.vat', '26400.00')->where('rows.0.vat_code', '2301')
            ->where('rows.0.receivable', '166400.00')->where('rows.0.receivable_code', '1201')
            ->where('rows.0.product', 'Kopi')->where('rows.0.qty', '2.00')->where('rows.0.price', '100000.00')->where('rows.0.pct', '10.00')
            // second line: invoice-level amounts are blank so a column sum never double counts
            ->where('rows.1.no', 2)->where('rows.1.product', 'Konsultasi')->where('rows.1.revenue', '50000.00')
            ->where('rows.1.bank_cash', null)->where('rows.1.vat', null)->where('rows.1.receivable', null)
            // other income row
            ->where('rows.2.product', 'Ongkir')->where('rows.2.revenue', '10000.00')->where('rows.2.revenue_code', '4104')->where('rows.2.qty', null));
    }

    public function test_totals_exclude_reversed_invoices(): void
    {
        $a = $this->invoice();
        $this->simple('t-2', [], '5000');
        $totals = fn () => $this->get('/transactions/invoices')->viewData('page')['props']['totals'];

        $this->assertSame(['bank_cash' => '100000.00', 'discount' => '20000.00', 'revenue' => '265000.00', 'vat' => '26400.00', 'receivable' => '171400.00', 'invoices' => 2], $totals());

        $this->post("/transactions/invoices/{$a->id}/reverse", ['reason' => 'Salah customer'])->assertSessionHasNoErrors();
        $this->assertSame(['bank_cash' => '0.00', 'discount' => '0.00', 'revenue' => '5000.00', 'vat' => '0.00', 'receivable' => '5000.00', 'invoices' => 1], $totals());
    }

    public function test_row_numbers_continue_across_pages(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->simple("page-{$i}", ['issued_on' => '2026-02-'.str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT)]);
        }

        $this->get('/transactions/invoices')->assertInertia(fn ($p) => $p->has('rows', 25)->where('rows.0.no', 1)->where('rows.24.no', 25)->where('pagination.last', 2)->where('pagination.total', 26));
        $this->get('/transactions/invoices?page=2')->assertInertia(fn ($p) => $p->has('rows', 1)->where('rows.0.no', 26));
    }

    // ---- Filters ------------------------------------------------------------------

    public function test_search_branch_and_date_filters(): void
    {
        $this->simple('f-1', ['issued_on' => '2026-01-15'], '1000', 'Teh hijau');
        $this->invoice(['issued_on' => '2026-05-20']);
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->simple('f-3', ['branch_id' => $other->id, 'issued_on' => '2026-06-01']);

        $rows = fn (string $qs) => count($this->get("/transactions/invoices?{$qs}")->viewData('page')['props']['rows']);

        $this->assertSame(1, $rows('search=hijau'), 'matches a product line');
        $this->assertSame(3, $rows('search=Kopi'), 'a match returns every row of that invoice');
        $this->assertSame(3, $rows('search=Ongkir'), 'matches an other-income label');
        $this->assertSame(5, $rows('search=PT+Pembeli'), 'matches the customer on all three invoices');
        $this->assertSame(5, $rows('search=CUST-1'), 'matches the customer code');
        $this->assertSame(1, $rows("branch_id={$other->id}"));
        $this->assertSame(1, $rows('from=2026-01-01&to=2026-02-01'));
        $this->assertSame(0, $rows('search=tidak-ada'));
    }

    public function test_filter_values_are_validated_and_branch_scoped_users_only_see_their_branch(): void
    {
        $this->get('/transactions/invoices?from=bukan-tanggal')->assertSessionHasErrors('from');

        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $mine = $this->simple('b-1');
        $this->simple('b-2', ['branch_id' => $other->id]);

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $props = $this->get('/transactions/invoices')->viewData('page')['props'];
        $this->assertCount(1, $props['rows']);
        $this->assertCount(1, $props['branches']);
        $this->post("/transactions/invoices/{$mine->id}/reverse", ['reason' => 'coba'])->assertNotFound();
    }

    // ---- CSV ----------------------------------------------------------------------

    public function test_csv_export_matches_the_register_and_follows_filters(): void
    {
        $this->invoice();
        $this->simple('c-2', ['issued_on' => '2026-08-01']);

        $csv = $this->get('/transactions/invoices/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($csv, 3))));
        $this->assertCount(1 + 4, $lines, 'header + 3 rows + 1 row');
        $header = str_getcsv($lines[0]);
        $this->assertCount(27, $header);
        $this->assertSame('Aksi', $header[0]);
        $this->assertSame('Status', $header[26]);
        $this->assertSame('Total Bank/Cash', $header[10]);

        $filtered = $this->get('/transactions/invoices/export?from=2026-08-01')->streamedContent();
        $this->assertCount(2, array_values(array_filter(preg_split('/\r?\n/', substr($filtered, 3)))));
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        $evil = $this->company->customers()->create(['name' => '=HYPERLINK("http://x","klik")', 'code' => '@SUM']);
        $this->simple('evil', ['customer_id' => $evil->id], '1000', '+cmd|calc');

        $csv = $this->get('/transactions/invoices/export')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd|calc", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    // ---- Reversal -----------------------------------------------------------------

    public function test_reversal_mirrors_the_journal_marks_the_invoice_and_keeps_history(): void
    {
        $invoice = $this->invoice();

        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Harga salah', 'posted_on' => '2026-03-15'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('reversed', $invoice->status);
        $this->assertSame('Harga salah', $invoice->reversal_reason);
        $this->assertNotNull($invoice->reversed_at);

        $original = JournalEntry::with('lines')->findOrFail($invoice->journal_entry_id);
        $reversal = JournalEntry::with('lines')->findOrFail($invoice->reversal_journal_entry_id);
        $this->assertSame($original->id, $reversal->reversal_of_id);
        $this->assertSame('2026-03-15', $reversal->posted_on->toDateString());

        $net = [];
        foreach ([$original, $reversal] as $entry) {
            foreach ($entry->lines as $l) {
                $net[$l->account_id] = bcadd($net[$l->account_id] ?? '0', bcsub($l->debit, $l->credit, 2), 2);
            }
        }
        $this->assertSame([], array_filter($net, fn ($v) => bccomp($v, '0', 2) !== 0), 'every account nets to zero');
        $this->assertTrue(JournalEntry::whereKey($original->id)->exists(), 'the original journal is never deleted');

        // still visible in the register for audit, flagged
        $this->get('/transactions/invoices')->assertInertia(fn ($p) => $p->where('rows.0.status', 'reversed'));
    }

    public function test_reversed_invoice_leaves_receivables_and_aging(): void
    {
        $invoice = $this->simple('r-1', [], '7000');
        $this->assertCount(1, app(AgingReportService::class)->receivables(['company_id' => $this->company->id]));

        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Batal'])->assertSessionHasNoErrors();

        $this->assertCount(0, app(AgingReportService::class)->receivables(['company_id' => $this->company->id]));
        $this->get('/transactions/receivables')->assertInertia(fn ($p) => $p->has('invoices', 0));
    }

    public function test_reversal_rules(): void
    {
        $invoice = $this->invoice();

        $this->post("/transactions/invoices/{$invoice->id}/reverse", [])->assertSessionHasErrors('reason');
        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2026-03-01'])->assertSessionHasErrors('invoice');
        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2027-01-01'])->assertSessionHasErrors('invoice');

        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2026-03-10'])->assertSessionHasNoErrors();
        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Lagi'])->assertSessionHasErrors('invoice');
        $this->assertSame(2, JournalEntry::count(), 'exactly one reversal journal');
    }

    public function test_invoice_with_a_later_receipt_cannot_be_reversed(): void
    {
        $invoice = $this->simple('late', [], '10000');
        app(TransactionPostingService::class)->receipt([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'fiscal_period_id' => $this->period->id,
            'invoice_id' => $invoice->id, 'receipt_number' => 'R-1', 'received_on' => '2026-03-20', 'amount' => '4000',
            'cash_account_id' => $this->id('1111'), 'receivable_account_id' => $this->id('1201'), 'idempotency_key' => 'late-r1',
        ]);

        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Batal'])->assertSessionHasErrors('invoice');
        $this->assertSame('partial', $invoice->refresh()->status);
    }

    public function test_other_company_invoice_cannot_be_reversed_or_given_evidence(): void
    {
        $invoice = $this->simple('x-1');
        $this->actingAs(User::factory()->create(['company_id' => Company::create(['name' => 'Other', 'code' => 'OTH'])->id]));

        $this->post("/transactions/invoices/{$invoice->id}/reverse", ['reason' => 'Coba-coba'])->assertNotFound();
        $this->post("/transactions/invoices/{$invoice->id}/attachments", ['attachments' => [UploadedFile::fake()->image('a.png')]])->assertNotFound();
    }

    // ---- Evidence -----------------------------------------------------------------

    public function test_evidence_can_be_added_listed_limited_and_deleted(): void
    {
        $invoice = $this->simple('e-1');

        $this->post("/transactions/invoices/{$invoice->id}/attachments", ['attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.jpg')]])->assertSessionHasNoErrors();
        $this->get('/transactions/invoices')->assertInertia(fn ($p) => $p->has('rows.0.attachments', 2)->where('maxAttachments', 5));

        $this->post("/transactions/invoices/{$invoice->id}/attachments", ['attachments' => array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.png"), [1, 2, 3, 4])])->assertSessionHasErrors('attachments');
        $this->assertSame(2, Attachment::count());

        $first = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($first->path);
        $this->delete(route('attachments.destroy', $first))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first->path);
        $this->assertSame(1, Attachment::count());

        $this->actingAs(User::factory()->create(['company_id' => Company::create(['name' => 'Other', 'code' => 'OTH'])->id]));
        $this->delete(route('attachments.destroy', Attachment::firstOrFail()))->assertNotFound();
    }
}
