<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD 03-INPUTAN-TRANSAKSI.md #1 Input Invoice, end to end through HTTP:
 * discount as expense, per-line revenue groups, other income, PPN, initial payment,
 * automatic numbering and evidence attachments.
 */
class InvoiceInputTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Inv Co', 'code' => 'INV', 'default_vat_rate' => '11', 'default_pph23_rate' => '2']);
        $branch = $this->company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->customer = $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'CUST']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        $this->actingAs($this->user);
        Storage::fake('local');
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->period->id,
            'customer_id' => $this->customer->id,
            'issued_on' => '2026-03-10',
            'receivable_account_id' => $this->id('1201'),
            'discount_account_id' => $this->id('5103'),
            'idempotency_key' => 'inv-'.uniqid(),
            'vat_enabled' => true,
            'vat_rate' => '11',
            'vat_output_account_id' => $this->id('2301'),
            'pph23_rate' => '2',
            'payment_method' => 'bank',
            'paid_amount' => '100000',
            'payment_account_id' => $this->id('1111'),
            'lines' => [
                ['description' => 'Kopi', 'quantity' => '2', 'unit_price' => '100000', 'discount_percent' => '10', 'revenue_account_id' => $this->id('4101')],
                ['description' => 'Konsultasi', 'quantity' => '1', 'unit_price' => '50000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4102')],
            ],
            'other_incomes' => [['label' => 'Ongkir', 'account_id' => $this->id('4104'), 'amount' => '10000']],
        ], $override);
    }

    /** signed (debit - credit) per account code */
    private function journal(Invoice $invoice): array
    {
        $entry = JournalEntry::with('lines.account')->findOrFail($invoice->journal_entry_id);
        $out = [];
        foreach ($entry->lines as $line) {
            $out[$line->account->code] = bcsub($line->debit, $line->credit, 2);
        }

        return $out;
    }

    public function test_full_invoice_posts_gross_revenue_discount_expense_vat_and_initial_payment(): void
    {
        $this->post('/accounting/invoices', $this->payload())->assertSessionHasNoErrors();

        $invoice = Invoice::with('lines', 'otherIncomes', 'receiptAllocations')->firstOrFail();
        $this->assertSame('INV-2026-00001', $invoice->invoice_number);
        $this->assertSame('240000.00', $invoice->dpp);          // 180.000 + 50.000 + 10.000
        $this->assertSame('20000.00', $invoice->discount_total);
        $this->assertSame('10000.00', $invoice->other_income_total);
        $this->assertSame('26400.00', $invoice->vat_amount);     // 11% of DPP
        $this->assertSame('266400.00', $invoice->total);
        $this->assertSame('4800.00', $invoice->pph23_amount);    // estimate only, not booked (ADR-008)
        $this->assertSame('100000.00', $invoice->paid_amount);
        $this->assertSame('partial', $invoice->status);
        $this->assertCount(2, $invoice->lines);
        $this->assertCount(1, $invoice->otherIncomes);

        $this->assertEquals([
            '1111' => '100000.00',   // paid at issue
            '1201' => '166400.00',   // receivable = total - paid
            '5103' => '20000.00',    // discount shown as expense
            '4101' => '-200000.00',  // gross revenue, not net
            '4102' => '-50000.00',
            '4104' => '-10000.00',
            '2301' => '-26400.00',
        ], $this->journal($invoice));

        // AR subledger agrees with the GL: the initial payment is a real receipt.
        $this->assertSame(0, bccomp((string) $invoice->receiptAllocations->sum('amount'), '100000', 2));
    }

    public function test_journal_always_balances_and_pph23_is_not_booked_at_issue(): void
    {
        $this->post('/accounting/invoices', $this->payload())->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $sum = '0.00';
        foreach ($this->journal($invoice) as $signed) {
            $sum = bcadd($sum, $signed, 2);
        }
        $this->assertSame('0.00', $sum);
        $this->assertArrayNotHasKey('1404', $this->journal($invoice), 'PPh23 Dibayar di Muka only appears when the customer withholds');
    }

    public function test_numbers_are_sequential_and_unique_and_redirect_goes_to_the_invoice(): void
    {
        $first = $this->post('/accounting/invoices', $this->payload());
        $this->post('/accounting/invoices', $this->payload());

        $numbers = Invoice::orderBy('id')->pluck('invoice_number')->all();
        $this->assertSame(['INV-2026-00001', 'INV-2026-00002'], $numbers);
        $first->assertRedirect(route('transactions.invoices.show', Invoice::orderBy('id')->first()));
    }

    public function test_unpaid_and_fully_paid_statuses(): void
    {
        $this->post('/accounting/invoices', $this->payload(['paid_amount' => '0', 'payment_account_id' => null]))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', Invoice::firstOrFail()->status);
        $this->assertSame(0, Invoice::firstOrFail()->receiptAllocations()->count());

        $this->post('/accounting/invoices', $this->payload(['paid_amount' => '266400']))->assertSessionHasNoErrors();
        $paid = Invoice::orderByDesc('id')->firstOrFail();
        $this->assertSame('paid', $paid->status);
        $this->assertArrayNotHasKey('1201', $this->journal($paid), 'nothing left as receivable');
    }

    public function test_business_rule_failures_become_errors_and_write_nothing(): void
    {
        $this->post('/accounting/invoices', $this->payload(['paid_amount' => '266400.01']))->assertSessionHasErrors('invoice');
        $this->post('/accounting/invoices', $this->payload(['payment_account_id' => $this->id('1201')]))->assertSessionHasErrors('invoice');
        $this->post('/accounting/invoices', $this->payload(['lines' => [['revenue_account_id' => $this->id('5101')]]]))->assertSessionHasErrors('invoice');

        $this->period->update(['status' => 'closed']);
        $this->post('/accounting/invoices', $this->payload())->assertSessionHasErrors();

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_limits_15_lines_and_3_other_incomes(): void
    {
        $line = ['description' => 'X', 'quantity' => '1', 'unit_price' => '1000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')];
        $other = fn (string $n) => ['label' => $n, 'account_id' => $this->id('4104'), 'amount' => '100'];

        $this->post('/accounting/invoices', $this->payload(['lines' => array_fill(0, 16, $line)]))->assertSessionHasErrors('lines');
        $this->post('/accounting/invoices', $this->payload(['other_incomes' => [$other('a'), $other('b'), $other('c'), $other('d')]]))->assertSessionHasErrors('other_incomes');

        $this->post('/accounting/invoices', $this->payload(['paid_amount' => '0', 'payment_account_id' => null, 'lines' => array_fill(0, 15, $line)]))->assertSessionHasNoErrors();
        $this->assertSame(15, Invoice::firstOrFail()->lines()->count());
    }

    public function test_duplicate_submission_is_rejected(): void
    {
        $payload = $this->payload(['idempotency_key' => 'double-click']);

        $this->post('/accounting/invoices', $payload)->assertSessionHasNoErrors();
        $this->post('/accounting/invoices', $payload)->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_foreign_company_references_are_rejected(): void
    {
        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $foreignRevenue = $other->accounts()->create(['code' => '4101', 'name' => 'Penjualan', 'type' => 'income']);
        $foreignProduct = $other->products()->create(['sku' => 'P', 'name' => 'P', 'unit' => 'u', 'price' => 1, 'revenue_account_id' => $foreignRevenue->id]);

        $this->post('/accounting/invoices', $this->payload(['lines' => [['revenue_account_id' => $foreignRevenue->id]]]))->assertSessionHasErrors('lines.0.revenue_account_id');
        $this->post('/accounting/invoices', $this->payload(['lines' => [['product_id' => $foreignProduct->id]]]))->assertSessionHasErrors('lines.0.product_id');
        $this->post('/accounting/invoices', $this->payload(['discount_account_id' => $foreignRevenue->id]))->assertSessionHasErrors('discount_account_id');

        $this->assertSame(0, Invoice::count());
    }

    public function test_master_product_is_linked_to_the_line(): void
    {
        $product = $this->company->products()->create(['sku' => 'KP-1', 'name' => 'Kopi', 'unit' => 'cup', 'price' => '100000', 'revenue_account_id' => $this->id('4101')]);

        $this->post('/accounting/invoices', $this->payload(['lines' => [['product_id' => $product->id]]]))->assertSessionHasNoErrors();

        $this->assertSame($product->id, Invoice::firstOrFail()->lines()->first()->product_id);
    }

    // ---- Evidence ---------------------------------------------------------------

    public function test_up_to_five_evidence_files_are_stored_privately_and_served_to_the_owner_only(): void
    {
        $files = [
            UploadedFile::fake()->image('nota.png'),
            UploadedFile::fake()->createWithContent('kwitansi.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"),
        ];
        $this->post('/accounting/invoices', $this->payload(['attachments' => $files]))->assertSessionHasNoErrors();

        $attachments = Attachment::all();
        $this->assertCount(2, $attachments);
        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertStringNotContainsString('nota', $attachment->path, 'stored under a random name');
        }

        $this->get(route('attachments.show', $attachments[0]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $stranger = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $this->actingAs(User::factory()->create(['company_id' => $stranger->id]));
        $this->get(route('attachments.show', $attachments[0]))->assertNotFound();
    }

    public function test_more_than_five_files_and_disguised_executables_are_rejected_and_nothing_is_posted(): void
    {
        $six = array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.png"), range(1, 6));
        $this->post('/accounting/invoices', $this->payload(['attachments' => $six]))->assertSessionHasErrors('attachments');

        // A real file on disk: the fake() helper reports its MIME from the extension, a real upload is sniffed by content.
        $path = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00PE\x00\x00".str_repeat("\x00", 128));
        $evil = new UploadedFile($path, 'bukti.jpg', 'image/jpeg', null, true);
        $this->post('/accounting/invoices', $this->payload(['attachments' => [$evil]]))->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Attachment::count());
    }

    // ---- Pages ------------------------------------------------------------------

    public function test_create_page_ships_everything_the_form_needs(): void
    {
        $this->get('/transactions/invoices/create')->assertOk()->assertInertia(fn ($page) => $page
            ->component('accounting/invoice-form')
            ->where('nextNumber', 'INV-'.now()->year.'-00001')
            ->where('defaults.vat_rate', fn ($v) => (float) $v === 11.0)
            ->where('defaults.pph23_rate', fn ($v) => (float) $v === 2.0)
            ->whereNot('defaults.receivable_account_id', null)
            ->whereNot('defaults.vat_output_account_id', null)
            ->whereNot('defaults.discount_account_id', null)
            ->has('paymentMethods', 4)->has('products')->has('customers')->has('company'));
    }

    public function test_detail_and_print_views_are_company_scoped(): void
    {
        $this->post('/accounting/invoices', $this->payload())->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();

        $this->get(route('transactions.invoices.show', $invoice))->assertOk()
            ->assertInertia(fn ($p) => $p->component('accounting/invoice-detail')->has('invoice.other_incomes', 1)->has('invoice.lines', 2));
        $this->get(route('transactions.invoices.print', $invoice))->assertOk()
            ->assertInertia(fn ($p) => $p->component('accounting/invoice-print')->where('company.name', 'Inv Co')->where('invoice.invoice_number', $invoice->invoice_number));

        $stranger = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $this->actingAs(User::factory()->create(['company_id' => $stranger->id]));
        $this->get(route('transactions.invoices.print', $invoice))->assertNotFound();
        $this->get(route('transactions.invoices.show', $invoice))->assertNotFound();
    }
}
