<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PRD 03 #5 Input Pelunasan Piutang and 04 #4 Data Pelunasan Piutang.
 * PPh 23 is booked only when the customer withholds (ADR-008).
 */
class ReceivableSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Piutang Co', 'code' => 'PIU', 'default_vat_rate' => '11', 'default_pph23_rate' => '2']);
        $branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
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

    private function invoice(array $override = []): Invoice
    {
        $this->post('/accounting/invoices', array_replace([
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->period->id,
            'customer_id' => $this->customer->id,
            'issued_on' => '2026-03-10',
            'receivable_account_id' => $this->id('1201'),
            'idempotency_key' => 'inv-'.Str::uuid(),
            'vat_enabled' => false,
            'pph23_rate' => '2',
            'paid_amount' => '0',
            'lines' => [
                ['description' => 'Jasa', 'quantity' => '1', 'unit_price' => '100000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
            ],
        ], $override))->assertSessionHasNoErrors();

        return Invoice::query()->latest('id')->firstOrFail();
    }

    /** @param  array<string, mixed>  $override */
    private function settlement(Invoice $invoice, array $override = []): array
    {
        return array_replace([
            'invoice_id' => $invoice->id,
            'received_on' => '2026-03-15',
            'payment_method' => 'cash',
            'amount' => '100000',
            'pph23_amount' => '0',
            'description' => 'Pelunasan invoice',
            'idempotency_key' => (string) Str::uuid(),
        ], $override);
    }

    /** signed (debit - credit) per account code */
    private function journal(Receipt $receipt): array
    {
        $entry = JournalEntry::with('lines.account')->findOrFail($receipt->journal_entry_id);
        $out = [];
        foreach ($entry->lines as $line) {
            $out[$line->account->code] = bcsub((string) $line->debit, (string) $line->credit, 2);
        }

        return $out;
    }

    public function test_installments_pay_the_invoice_and_ignore_a_browser_branch(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBR']);
        $invoice = $this->invoice();

        $this->post('/transactions/receivables', $this->settlement($invoice, [
            'amount' => '40000',
            'branch_id' => $other->id,
            'receivable_account_id' => $this->id('1101'),
        ]))->assertRedirect(route('data.piutang.index'));

        $first = Receipt::where('receipt_number', 'like', 'PLT-%')->firstOrFail();
        $this->assertSame('PLT-2026-00001', $first->receipt_number);
        $this->assertSame($invoice->branch_id, $first->branch_id);
        $this->assertSame($invoice->receivable_account_id, $first->receivable_account_id);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('0.00', (string) $invoice->fresh()->paid_amount);
        $this->assertSame(['1101' => '40000.00', '1201' => '-40000.00'], $this->journal($first));

        $this->post('/transactions/receivables', $this->settlement($invoice, ['amount' => '60000']))->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->get('/data/d-piutang')->assertOk()->assertInertia(fn ($page) => $page
            ->component('accounting/data-piutang')
            ->has('columns', 16)
            ->where('columns.11', 'Jumlah Dibayar')
            ->where('rows.0.remaining', '0.00')
            ->where('rows.1.remaining', '60000.00')
            ->where('totals.amount', '100000.00')
            ->where('totals.count', 2));
    }

    public function test_overpayment_early_date_closed_period_and_other_branch_are_rejected(): void
    {
        $invoice = $this->invoice();

        $this->post('/transactions/receivables', $this->settlement($invoice, ['amount' => '100000.01']))->assertSessionHasErrors('amount');
        $this->post('/transactions/receivables', $this->settlement($invoice, ['received_on' => '2026-03-01']))->assertSessionHasErrors('amount');

        $this->period->update(['status' => 'closed']);
        $this->post('/transactions/receivables', $this->settlement($invoice))->assertSessionHasErrors('amount');
        $this->period->update(['status' => 'open']);

        $other = $this->company->branches()->create(['name' => 'Lain', 'code' => 'LAIN']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $this->post('/transactions/receivables', $this->settlement($invoice))->assertSessionHasErrors('invoice_id');

        $this->assertSame(0, Receipt::where('receipt_number', 'like', 'PLT-%')->count());
    }

    public function test_withheld_pph23_books_three_lines_and_reduces_the_receivable_by_the_gross(): void
    {
        $invoice = $this->invoice();

        $this->post('/transactions/receivables', $this->settlement($invoice, [
            'amount' => '48000',
            'pph23_amount' => '2000',
        ]))->assertRedirect();

        $receipt = Receipt::where('receipt_number', 'like', 'PLT-%')->firstOrFail();
        $this->assertSame('2000.00', (string) $receipt->pph23_amount);
        $this->assertSame('50000.00', (string) $receipt->allocations()->first()->amount);
        $this->assertSame([
            '1101' => '48000.00',
            '1404' => '2000.00',
            '1201' => '-50000.00',
        ], $this->journal($receipt));
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertCount(3, JournalEntry::find($receipt->journal_entry_id)->lines);

        $this->post('/transactions/receivables', $this->settlement($invoice, [
            'amount' => '1000',
            'pph23_amount' => '0.01',
        ]))->assertSessionHasErrors('amount');

        $this->get('/transactions/receivables')->assertInertia(fn ($page) => $page
            ->component('accounting/receivable-form')
            ->where('invoices.0.remaining', '50000.00')
            ->where('invoices.0.pph23_left', '0.00')
            ->where('summary.invoices', 1));
    }

    public function test_posting_is_idempotent_and_reversal_frees_the_invoice(): void
    {
        $invoice = $this->invoice();
        $payload = $this->settlement($invoice, ['description' => '=HYPERLINK("http://evil")']);

        $this->post('/transactions/receivables', $payload)->assertRedirect();
        $this->post('/transactions/receivables', $payload)->assertSessionHasErrors('amount');
        $this->assertSame(1, Receipt::where('receipt_number', 'like', 'PLT-%')->count());
        $this->assertSame('paid', $invoice->fresh()->status);

        $receipt = Receipt::where('receipt_number', 'like', 'PLT-%')->firstOrFail();
        $this->post("/data/d-piutang/{$receipt->id}/reverse", [
            'reason' => 'Nominal salah',
            'posted_on' => '2026-03-16',
        ])->assertSessionHasNoErrors();

        $this->assertSame('reversed', $receipt->fresh()->status);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertNotNull($receipt->fresh()->reversal_journal_entry_id);
        $this->assertTrue(JournalEntry::whereKey($receipt->journal_entry_id)->exists());

        $this->post("/transactions/invoices/{$invoice->id}/reverse", [
            'reason' => 'Invoice batal',
            'posted_on' => '2026-03-16',
        ])->assertSessionHasNoErrors();
        $this->assertSame('reversed', $invoice->fresh()->status);

        $this->get('/data/d-piutang')->assertInertia(fn ($page) => $page
            ->where('rows.0.status', 'reversed')
            ->where('totals.count', 0)
            ->where('totals.amount', '0.00'));

        $csv = $this->get('/data/d-piutang/export')->assertOk()->streamedContent();
        $lines = preg_split("/\r\n|\n|\r/", trim($csv));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(17, str_getcsv($lines[0]), '16 columns + Status');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_initial_invoice_payment_is_not_listed_and_companies_stay_isolated(): void
    {
        $invoice = $this->invoice(['paid_amount' => '20000', 'payment_method' => 'cash', 'payment_account_id' => $this->id('1101')]);
        $this->assertSame('partial', $invoice->status);

        $this->post('/transactions/receivables', $this->settlement($invoice, ['amount' => '80000']))->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, Receipt::where('receipt_number', 'like', 'PLT-%')->count());

        $this->get('/data/d-piutang')->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.amount', '80000.00')
            ->where('rows.0.remaining', '0.00'));

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN2']);
        $branch = $other->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->actingAs(User::factory()->create(['company_id' => $other->id, 'branch_id' => $branch->id]));

        $this->get('/data/d-piutang')->assertInertia(fn ($page) => $page->has('rows', 0));
        $this->post('/transactions/receivables', $this->settlement($invoice))->assertSessionHasErrors('invoice_id');
    }

    public function test_evidence_is_limited_to_five_files(): void
    {
        $invoice = $this->invoice();
        $files = array_map(fn ($i) => UploadedFile::fake()->create("bukti-{$i}.pdf", 20, 'application/pdf'), range(1, 6));

        $this->post('/transactions/receivables', $this->settlement($invoice, ['attachments' => $files]))->assertSessionHasErrors('attachments');
        $this->assertSame(0, Receipt::count());

        array_pop($files);
        $this->post('/transactions/receivables', $this->settlement($invoice, ['attachments' => $files]))->assertRedirect();
        $this->assertSame(5, Receipt::first()->attachments()->count());
    }
}
