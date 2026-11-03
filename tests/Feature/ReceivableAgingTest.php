<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\JournalPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PRD 05 #8 Laporan Piutang: buckets <=30 / 31-60 / >60, as-of payments, and the GL reconciliation.
 */
class ReceivableAgingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Aging Co', 'code' => 'AGE']);
        $branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->periodId = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id;
        $this->customer = $this->company->customers()->create(['name' => 'PT Pembeli', 'code' => 'CUST']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        $this->actingAs($this->user);
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    private function invoice(array $override = []): Invoice
    {
        $this->post('/accounting/invoices', array_replace([
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->periodId,
            'customer_id' => $this->customer->id,
            'issued_on' => '2026-05-02',
            'receivable_account_id' => $this->id('1201'),
            'idempotency_key' => 'inv-'.Str::uuid(),
            'vat_enabled' => false,
            'pph23_rate' => '0',
            'paid_amount' => '0',
            'lines' => [
                ['description' => 'Jasa', 'quantity' => '1', 'unit_price' => '10000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
            ],
        ], $override))->assertSessionHasNoErrors();

        return Invoice::query()->latest('id')->firstOrFail();
    }

    public function test_buckets_hide_paid_invoices_until_the_filter_asks_for_them(): void
    {
        $this->invoice(['issued_on' => '2026-05-02', 'lines' => [
            ['description' => 'Baru', 'quantity' => '1', 'unit_price' => '10000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
        ]]);
        $this->invoice(['issued_on' => '2026-04-17', 'lines' => [
            ['description' => 'Tengah', 'quantity' => '1', 'unit_price' => '20000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
        ]]);
        $this->invoice(['issued_on' => '2026-03-18', 'lines' => [
            ['description' => 'Lama', 'quantity' => '1', 'unit_price' => '30000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
        ]]);
        $paid = $this->invoice([
            'issued_on' => '2026-05-01',
            'paid_amount' => '5000',
            'payment_account_id' => $this->id('1101'),
            'lines' => [
                ['description' => 'Lunas', 'quantity' => '1', 'unit_price' => '5000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
            ],
        ]);

        $response = $this->get('/accounting/aging?as_of=2026-06-01')->assertOk();
        $this->assertSame('accounting/aging', $response->viewData('page')['component']);
        $page = $response->viewData('page')['props'];
        $byDays = collect($page['rows'])->keyBy('days');
        $this->assertSame(['30', '45', '75'], $byDays->keys()->map(fn ($d) => (string) $d)->sort()->values()->all());
        $this->assertSame('≤30', $byDays[30]['bucket']);
        $this->assertSame('31–60', $byDays[45]['bucket']);
        $this->assertSame('>60', $byDays[75]['bucket']);
        $this->assertSame('10000.00', $page['buckets']['≤30']);
        $this->assertSame('20000.00', $page['buckets']['31–60']);
        $this->assertSame('30000.00', $page['buckets']['>60']);
        $this->assertSame('60000.00', $page['reconciliation']['documents']);
        $this->assertSame('60000.00', $page['reconciliation']['ledger']);
        $this->assertSame('0.00', $page['reconciliation']['difference']);

        $paidRows = $this->get('/accounting/aging?as_of=2026-06-01&status=paid')->viewData('page')['props']['rows'];
        $this->assertSame([$paid->invoice_number], collect($paidRows)->pluck('number')->all());
        $this->assertSame('Lunas', $paidRows[0]['status']);
        $this->assertNull($paidRows[0]['receipt_url']);
        $paidPage = $this->get('/accounting/aging?as_of=2026-06-01&status=paid')->viewData('page')['props'];
        $this->assertSame('60000.00', $paidPage['reconciliation']['documents']);
        $this->assertSame('0.00', $paidPage['reconciliation']['difference']);
    }

    public function test_a_receipt_after_the_as_of_date_is_ignored_and_a_reversal_drops_the_invoice(): void
    {
        $open = $this->invoice(['issued_on' => '2026-05-20', 'lines' => [
            ['description' => 'Belum', 'quantity' => '1', 'unit_price' => '8000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
        ]]);
        $this->post('/transactions/receivables', [
            'invoice_id' => $open->id,
            'received_on' => '2026-06-15',
            'payment_method' => 'cash',
            'amount' => '8000',
            'pph23_amount' => '0',
            'description' => 'Nanti',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $row = $this->get('/accounting/aging?as_of=2026-06-01')->viewData('page')['props']['rows'][0];
        $this->assertSame('8000.00', $row['remaining']);
        $this->assertSame('Belum', $row['status']);
        $this->assertNull($row['last_payment']);

        $later = $this->get('/accounting/aging?as_of=2026-06-15')->viewData('page')['props']['rows'];
        $this->assertSame([], $later);

        $gone = $this->invoice(['issued_on' => '2026-04-01']);
        $this->post("/transactions/invoices/{$gone->id}/reverse", ['reason' => 'Batal', 'posted_on' => '2026-04-02'])->assertSessionHasNoErrors();
        $numbers = collect($this->get('/accounting/aging?as_of=2026-06-01&status=all')->viewData('page')['props']['rows'])->pluck('number');
        $this->assertFalse($numbers->contains($gone->invoice_number));
    }

    public function test_company_and_branch_stay_isolated_and_a_manual_balance_is_the_reconciliation_gap(): void
    {
        $this->invoice();
        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $otherBranch = $other->branches()->create(['name' => 'Lain', 'code' => 'L']);
        $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        $this->actingAs(User::factory()->create(['company_id' => $other->id, 'branch_id' => $otherBranch->id]));
        $this->assertSame([], $this->get('/accounting/aging?as_of=2026-06-01')->viewData('page')['props']['rows']);

        $this->actingAs($this->user);
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id,
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-01-15',
            'description' => 'Saldo awal piutang',
            'idempotency_key' => 'ar-open',
            'source_kind' => 'manual',
            'lines' => [
                ['account_id' => $this->id('1201'), 'debit' => '2500', 'credit' => '0'],
                ['account_id' => $this->id('3101'), 'debit' => '0', 'credit' => '2500'],
            ],
        ]);
        $report = app(AgingReportService::class)->build([
            'company_id' => $this->company->id, 'branch_id' => $this->user->branch_id, 'as_of' => '2026-06-01',
        ]);
        $this->assertSame('10000.00', $report['reconciliation']['documents']);
        $this->assertSame('12500.00', $report['reconciliation']['ledger']);
        $this->assertSame('2500.00', $report['reconciliation']['difference']);

        $second = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBR']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
        $this->invoice(['branch_id' => $second->id, 'issued_on' => '2026-05-10', 'lines' => [
            ['description' => 'Cabang', 'quantity' => '1', 'unit_price' => '7000', 'discount_percent' => '0', 'revenue_account_id' => $this->id('4101')],
        ]]);
        $this->actingAs($this->user);
        $numbers = collect($this->get('/accounting/aging?as_of=2026-06-01')->viewData('page')['props']['rows'])->pluck('number');
        $this->assertCount(1, $numbers);
    }
}
