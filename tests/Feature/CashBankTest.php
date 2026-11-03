<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\JournalEntry;
use App\Models\TemplateOption;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\LedgerService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD 03 #7 Input Pindah Saldo Kas/Bank and 04 #7 Data Kas & Bank.
 * Bayar hutang settles open expenses FIFO per payable account (ADR-011) in the same journal.
 */
class CashBankTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private $branch;

    private $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Kas Co', 'code' => 'KAS']);
        $this->branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
        Storage::fake('local');
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_replace([
            'branch_id' => $this->branch->id,
            'posted_on' => '2026-03-10',
            'transaction_type' => 'transfer',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('1111'),
            'from_amount' => '250000',
            'to_amount' => '250000',
            'description' => 'Transfer kas ke bank',
            'idempotency_key' => 'kas-'.uniqid(),
        ], $override);
    }

    private function debt(string $date, string $amount): Expense
    {
        $this->post('/accounting/expenses', [
            'branch_id' => $this->branch->id, 'expense_date' => $date, 'payment_method' => 'utang',
            'expense_account_id' => $this->id('5209'), 'description' => 'Hutang '.$date, 'amount' => $amount,
            'idempotency_key' => 'exp-'.uniqid(),
        ])->assertSessionHasNoErrors();

        return Expense::orderByDesc('id')->firstOrFail();
    }

    public function test_each_treasury_type_posts_a_balanced_journal_on_its_categories(): void
    {
        $types = TemplateOption::where('company_id', $this->company->id)->where('group', 'treasury_type')->orderBy('sort_order')->get();
        $this->assertCount(11, $types);

        foreach ($types as $type) {
            if ($type->code === 'bayar_hutang') {
                $this->debt('2026-03-01', '10000');
            }
            $from = $this->categoryAccount($type->meta['from_category']);
            $to = $this->categoryAccount($type->meta['to_category'], $from);
            $this->post('/transactions/cash-bank', $this->payload([
                'transaction_type' => $type->code,
                'from_account_id' => $from,
                'to_account_id' => $to,
                'from_amount' => '10000',
                'to_amount' => '10000',
                'description' => $type->label,
                'posted_on' => '2026-04-02',
            ]))->assertSessionHasNoErrors()->assertRedirect(route('data.kasbank.index'));
        }

        $this->assertSame(11, TreasuryTransaction::where('transaction_type', '!=', 'movement')->count());
        $this->assertSame(
            ['KAS-2026-00001', 'KAS-2026-00011'],
            [TreasuryTransaction::orderBy('reference')->value('reference'), TreasuryTransaction::orderByDesc('reference')->value('reference')],
        );
        foreach (TreasuryTransaction::with('journalEntry.lines')->get() as $tx) {
            $lines = $tx->journalEntry->lines;
            $this->assertEquals(0, (float) $lines->sum('debit') - (float) $lines->sum('credit'));
            $this->assertSame('treasury', $tx->journalEntry->source_kind);
            $this->assertSame($tx->reference, $tx->journalEntry->reference);
        }
    }

    public function test_accounts_outside_the_type_and_unequal_amounts_are_rejected(): void
    {
        $this->post('/transactions/cash-bank', $this->payload([
            'from_account_id' => $this->id('2101'),
            'to_account_id' => $this->id('1101'),
        ]))->assertSessionHasErrors('cashbank');

        $this->post('/transactions/cash-bank', $this->payload([
            'from_amount' => '100', 'to_amount' => '90',
        ]))->assertSessionHasErrors('to_amount');

        $this->post('/transactions/cash-bank', $this->payload([
            'to_account_id' => $this->id('1101'),
        ]))->assertSessionHasErrors('from_account_id');

        $this->assertSame(0, TreasuryTransaction::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_closed_period_and_other_branch_are_rejected(): void
    {
        $this->period->update(['status' => 'closed']);
        $this->post('/transactions/cash-bank', $this->payload())->assertSessionHasErrors('posted_on');

        $this->period->update(['status' => 'open']);
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $this->post('/transactions/cash-bank', $this->payload())->assertSessionHasErrors('branch_id');
        $this->assertSame(0, TreasuryTransaction::count());
    }

    public function test_duplicate_submit_posts_once(): void
    {
        $payload = $this->payload(['idempotency_key' => 'same-key']);
        $this->post('/transactions/cash-bank', $payload)->assertSessionHasNoErrors();
        $this->post('/transactions/cash-bank', $payload)->assertSessionHasErrors('cashbank');

        $this->assertSame(1, TreasuryTransaction::count());
        $this->assertSame(1, JournalEntry::where('source_kind', 'treasury')->count());
    }

    public function test_debt_payment_is_fifo_per_account_in_one_journal(): void
    {
        $older = $this->debt('2026-01-15', '100000');
        $newer = $this->debt('2026-02-15', '80000');

        $this->post('/transactions/cash-bank', $this->payload([
            'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('2101'),
            'from_amount' => '120000',
            'to_amount' => '120000',
            'description' => 'Bayar hutang Maret',
            'posted_on' => '2026-03-20',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('paid', $older->fresh()->status);
        $this->assertSame('partial', $newer->fresh()->status);
        $this->assertSame('20000.00', (string) $newer->fresh()->payments()->first()->amount);
        $this->assertSame(1, JournalEntry::where('source_kind', 'treasury')->count(), 'one journal, not one per bill');
        $this->assertSame(2, ExpensePayment::count());

        $lines = JournalEntry::where('source_kind', 'treasury')->first()->lines->load('account');
        $net = [];
        foreach ($lines as $line) {
            $net[$line->account->code] = bcsub((string) $line->debit, (string) $line->credit, 2);
        }
        $this->assertSame(['2101' => '120000.00', '1101' => '-120000.00'], $net);

        $this->post('/transactions/cash-bank', $this->payload([
            'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('2101'),
            'from_amount' => '70000',
            'to_amount' => '70000',
            'posted_on' => '2026-03-21',
        ]))->assertSessionHasErrors('cashbank');
        $this->assertSame('partial', $newer->fresh()->status);
    }

    public function test_payment_within_the_ledger_can_exceed_open_bills(): void
    {
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'fiscal_period_id' => $this->period->id,
            'posted_on' => '2026-01-02', 'description' => 'Saldo awal hutang', 'idempotency_key' => 'open-ap',
            'source_kind' => 'opening', 'lines' => [
                ['account_id' => $this->id('1101'), 'debit' => '50000', 'credit' => '0'],
                ['account_id' => $this->id('2101'), 'debit' => '0', 'credit' => '50000'],
            ],
        ]);
        $expense = $this->debt('2026-01-20', '100000');

        $this->post('/transactions/cash-bank', $this->payload([
            'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('2101'),
            'from_amount' => '130000',
            'to_amount' => '130000',
            'description' => 'Lunas tagihan plus saldo awal',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame(1, ExpensePayment::count());
        $this->assertSame('100000.00', (string) ExpensePayment::first()->amount);
        $this->assertSame('130000.00', (string) TreasuryTransaction::first()->amount);
    }

    public function test_reversal_restores_the_expense_and_nets_the_journal(): void
    {
        $expense = $this->debt('2026-01-15', '100000');
        $this->post('/transactions/cash-bank', $this->payload([
            'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'),
            'to_account_id' => $this->id('2101'),
            'from_amount' => '40000',
            'to_amount' => '40000',
            'posted_on' => '2026-03-20',
        ]))->assertSessionHasNoErrors();
        $tx = TreasuryTransaction::firstOrFail();

        $this->post("/data/d-kasbank/{$tx->id}/reverse", [])->assertSessionHasErrors('reason');
        $this->post("/data/d-kasbank/{$tx->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2026-03-01'])->assertSessionHasErrors('cashbank');
        $this->post("/data/d-kasbank/{$tx->id}/reverse", ['reason' => 'Salah nominal', 'posted_on' => '2026-03-25'])->assertSessionHasNoErrors();

        $this->assertSame('reversed', $tx->fresh()->status);
        $this->assertSame('open', $expense->fresh()->status);
        $this->assertNotNull(ExpensePayment::first()->reversed_at);
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Batal beban', 'posted_on' => '2026-03-26'])->assertSessionHasNoErrors();
        $this->post("/data/d-kasbank/{$tx->id}/reverse", ['reason' => 'Lagi'])->assertSessionHasErrors('cashbank');

        $net = [];
        foreach ([$tx->journal_entry_id, $tx->fresh()->reversal_journal_entry_id] as $id) {
            foreach (JournalEntry::with('lines')->find($id)->lines as $line) {
                $net[$line->account_id] = bcadd($net[$line->account_id] ?? '0', bcsub((string) $line->debit, (string) $line->credit, 2), 2);
            }
        }
        $this->assertSame([], array_filter($net, fn ($v) => bccomp($v, '0', 2) !== 0));
    }

    public function test_register_has_the_14_columns_and_exports_safely(): void
    {
        $this->post('/transactions/cash-bank', $this->payload(['description' => '=HYPERLINK("http://evil")']))->assertSessionHasNoErrors();

        $this->get('/data/d-kasbank')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/data-kas-bank')
            ->where('columns', fn ($c) => count($c) === 14)
            ->where('rows.0.to_code', '1111')
            ->where('rows.0.from_code', '1101')
            ->where('rows.0.debit', '250000.00')
            ->where('rows.0.credit', '250000.00')
            ->where('totals.amount', '250000.00')
            ->where('totals.count', 1));

        $csv = $this->get('/data/d-kasbank/export')->assertOk()->streamedContent();
        $lines = preg_split("/\r\n|\n|\r/", trim($csv));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(15, str_getcsv($lines[0]), '14 columns + Status');
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        $url = app(LedgerService::class)->sourceUrls(collect([(object) ['line_id' => 1, 'source_kind' => 'treasury', 'source_id' => '1', 'reference' => 'KAS-2026-00001']]));
        $this->assertSame('/data/d-kasbank?search=KAS-2026-00001', $url[1]);
    }

    public function test_form_lists_types_and_cash_balances(): void
    {
        $this->post('/transactions/cash-bank', $this->payload())->assertSessionHasNoErrors();

        $this->get('/transactions/cash-bank')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/kas-bank-form')
            ->has('types', 11)
            ->where('balances', function ($rows) {
                $byAccount = collect($rows)->where('branch_id', $this->branch->id)->keyBy('account_id');

                return $byAccount[$this->id('1101')]['balance'] === '-250000.00'
                    && $byAccount[$this->id('1111')]['balance'] === '250000.00';
            }));
    }

    public function test_other_company_and_branch_cannot_touch_the_register(): void
    {
        $this->post('/transactions/cash-bank', $this->payload())->assertSessionHasNoErrors();
        $tx = TreasuryTransaction::firstOrFail();

        $this->actingAs(User::factory()->create(['company_id' => Company::create(['name' => 'Other', 'code' => 'OTH'])->id]));
        $this->post("/data/d-kasbank/{$tx->id}/reverse", ['reason' => 'Coba-coba'])->assertNotFound();

        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $this->get('/data/d-kasbank')->assertOk()->assertInertia(fn ($p) => $p->where('totals.count', 0)->where('pagination.total', 0));
        $this->post("/data/d-kasbank/{$tx->id}/reverse", ['reason' => 'Coba-coba'])->assertNotFound();
    }

    public function test_evidence_is_stored_with_the_movement(): void
    {
        $this->post('/transactions/cash-bank', $this->payload([
            'attachments' => [UploadedFile::fake()->image('bukti.png')],
        ]))->assertSessionHasNoErrors();

        $this->get('/data/d-kasbank')->assertInertia(fn ($p) => $p->has('rows.0.attachments', 1)->where('maxAttachments', 5));
    }

    public function test_pages_require_a_company(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/transactions/cash-bank')->assertForbidden();
        $this->get('/data/d-kasbank')->assertForbidden();
    }

    private function categoryAccount(string $category, ?int $except = null): int
    {
        $ids = TemplateOption::where('company_id', $this->company->id)
            ->where('group', 'treasury_category')
            ->where('is_active', true)
            ->get()
            ->filter(fn ($option) => ($option->meta['category'] ?? null) === $category)
            ->pluck('account_id')
            ->filter(fn ($id) => (int) $id !== (int) $except)
            ->values();

        return (int) $ids->first();
    }
}
