<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD 03 #3 Input Beban and 04 #2 Data Beban: Dr expense / Cr cash, bank or payable; the payment
 * account comes from Template Jurnal; the register ties to the journal; corrections are reversals.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private FiscalPeriod $period;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Exp Co', 'code' => 'EXP']);
        $this->branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
        Storage::fake('local');
    }

    private function id(string $code, ?Company $company = null): int
    {
        return ($company ?? $this->company)->accounts()->where('code', $code)->value('id');
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'branch_id' => $this->branch->id, 'expense_date' => '2026-03-05', 'payment_method' => 'cash',
            'expense_account_id' => $this->id('5209'), 'description' => 'Bayar parkir', 'amount' => '75000.50',
            'idempotency_key' => 'exp-'.uniqid(),
        ], $override);
    }

    private function make(array $override = []): Expense
    {
        $this->post('/accounting/expenses', $this->payload($override))->assertSessionHasNoErrors();

        return Expense::orderByDesc('id')->firstOrFail();
    }

    /** signed (debit - credit) per account code of the expense journal */
    private function journal(Expense $expense): array
    {
        $out = [];
        foreach (JournalEntry::with('lines.account')->findOrFail($expense->journal_entry_id)->lines as $l) {
            $out[$l->account->code] = bcsub($l->debit, $l->credit, 2);
        }

        return $out;
    }

    // ---- Input Beban --------------------------------------------------------------

    public function test_cash_expense_debits_the_expense_and_credits_cash(): void
    {
        $response = $this->post('/accounting/expenses', $this->payload());
        $expense = Expense::firstOrFail();

        $response->assertRedirect(route('transactions.expenses.show', $expense));
        $this->assertSame('paid', $expense->status);
        $this->assertSame('BBN-2026-00001', $expense->reference);
        $this->assertSame('Bayar parkir', $expense->description);
        $this->assertSame('75000.50', $expense->amount);
        $this->assertSame($this->id('1101'), $expense->settlement_account_id);
        $this->assertSame(['5209' => '75000.50', '1101' => '-75000.50'], $this->journal($expense));

        $entry = JournalEntry::findOrFail($expense->journal_entry_id);
        $this->assertSame('expense', $entry->source_kind);
        $this->assertSame('BBN-2026-00001', $entry->reference);
    }

    public function test_bank_and_debt_follow_the_template_and_debt_stays_open(): void
    {
        $bank = $this->make(['payment_method' => 'bank']);
        $debt = $this->make(['payment_method' => 'utang', 'amount' => '200000']);

        $this->assertSame($this->id('1111'), $bank->settlement_account_id);
        $this->assertSame('paid', $bank->status);
        $this->assertSame($this->id('2101'), $debt->settlement_account_id);
        $this->assertSame('open', $debt->status, 'on credit the expense is a debt until paid');
        $this->assertSame(['5209' => '200000.00', '2101' => '-200000.00'], $this->journal($debt));
        $this->assertSame(['BBN-2026-00001', 'BBN-2026-00002'], Expense::orderBy('id')->pluck('reference')->all());
    }

    public function test_the_payment_account_cannot_be_chosen_from_the_browser(): void
    {
        $this->post('/accounting/expenses', $this->payload(['payment_account_id' => $this->id('1111'), 'settlement_account_id' => $this->id('1111')]))->assertSessionHasNoErrors();

        $this->assertSame($this->id('1101'), Expense::firstOrFail()->settlement_account_id, 'cash method maps to 1101 regardless of what was sent');
    }

    public function test_changing_the_template_changes_where_the_money_comes_from(): void
    {
        $this->company->templateOptions()->where(['group' => 'outflow_payment', 'code' => 'cash'])->update(['account_id' => $this->id('1102')]);

        $this->assertSame($this->id('1102'), $this->make()->settlement_account_id);
    }

    public function test_validation_and_account_rules(): void
    {
        $foreign = Company::create(['name' => 'Other', 'code' => 'OTH'])->accounts()->create(['code' => '5209', 'name' => 'X', 'type' => 'expense']);
        $this->company->accounts()->where('code', '5208')->update(['is_active' => false]);
        $cases = [
            'asset account' => [['expense_account_id' => $this->id('1101')], 'expense_account_id'],
            'revenue account' => [['expense_account_id' => $this->id('4101')], 'expense_account_id'],
            'foreign account' => [['expense_account_id' => $foreign->id], 'expense_account_id'],
            'inactive account' => [['expense_account_id' => $this->id('5208')], 'expense_account_id'],
            'unknown method' => [['payment_method' => 'crypto'], 'payment_method'],
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-5'], 'amount'],
            'three decimals' => [['amount' => '1.005'], 'amount'],
            'no description' => [['description' => ''], 'description'],
            'bad date' => [['expense_date' => 'kemarin'], 'expense_date'],
        ];

        foreach ($cases as [$override, $field]) {
            $this->post('/accounting/expenses', $this->payload($override))->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_date_must_fall_in_an_open_fiscal_year(): void
    {
        $this->post('/accounting/expenses', $this->payload(['expense_date' => '2027-02-01']))->assertSessionHasErrors('expense_date');

        $this->period->update(['status' => 'closed']);
        $this->post('/accounting/expenses', $this->payload())->assertSessionHasErrors('expense_date');
        $this->assertSame(0, Expense::count());
    }

    public function test_method_without_an_account_mapping_is_refused(): void
    {
        $this->company->templateOptions()->where(['group' => 'outflow_payment', 'code' => 'bank'])->update(['account_id' => null]);

        $this->post('/accounting/expenses', $this->payload(['payment_method' => 'bank']))->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Expense::count());
    }

    public function test_a_branch_bound_user_can_only_post_for_their_branch(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));

        $this->post('/accounting/expenses', $this->payload())->assertSessionHasErrors('branch_id');
        $this->post('/accounting/expenses', $this->payload(['branch_id' => $other->id]))->assertSessionHasNoErrors();

        $this->assertSame([$other->id], Expense::pluck('branch_id')->all());
    }

    public function test_double_submit_is_rejected(): void
    {
        $payload = $this->payload(['idempotency_key' => 'once']);

        $this->post('/accounting/expenses', $payload)->assertSessionHasNoErrors();
        $this->post('/accounting/expenses', $payload)->assertSessionHasErrors('expense');

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_evidence_is_stored_with_the_expense_or_nothing_is_posted(): void
    {
        $this->post('/accounting/expenses', $this->payload(['attachments' => [UploadedFile::fake()->image('nota.png'), UploadedFile::fake()->image('struk.jpg')]]))->assertSessionHasNoErrors();
        $this->assertSame(2, Attachment::count());
        $this->assertSame(Expense::firstOrFail()->id, Attachment::firstOrFail()->attachable_id);

        $six = array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.png"), range(1, 6));
        $this->post('/accounting/expenses', $this->payload(['attachments' => $six]))->assertSessionHasErrors('attachments');

        $path = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00PE\x00\x00".str_repeat("\x00", 128));
        $this->post('/accounting/expenses', $this->payload(['attachments' => [new UploadedFile($path, 'bukti.jpg', 'image/jpeg', null, true)]]))->assertSessionHasErrors('attachments.0');

        $this->assertSame(1, Expense::count());
    }

    public function test_form_and_detail_pages_have_what_they_need(): void
    {
        $expense = $this->make(['payment_method' => 'utang']);

        $this->get('/transactions/expenses/create')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/expense-form')
            ->where('accounts', fn ($a) => collect($a)->every(fn ($x) => str_starts_with($x['code'], '5')) && count($a) > 10)
            ->where('paymentMethods', fn ($m) => collect($m)->pluck('settles', 'code')->all() === ['cash' => 'now', 'bank' => 'now', 'utang' => 'debt'])
            ->has('branches', 1));

        $this->get(route('transactions.expenses.show', $expense))->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/expense-detail')->where('expense.reference', 'BBN-2026-00001')->has('journalEntry.lines', 2));

        $this->actingAs(User::factory()->create(['company_id' => Company::create(['name' => 'Other', 'code' => 'OTH'])->id]));
        $this->get(route('transactions.expenses.show', $expense))->assertNotFound();
    }

    // ---- Data Beban ---------------------------------------------------------------

    public function test_register_has_the_14_prd_columns_and_ties_to_the_journal(): void
    {
        $this->make(['description' => 'Parkir']);
        $this->make(['payment_method' => 'utang', 'amount' => '200000', 'expense_account_id' => $this->id('5203'), 'description' => 'Listrik', 'expense_date' => '2026-04-02']);

        $p = $this->get('/transactions/expenses')->assertOk()->viewData('page')['props'];

        $this->assertCount(14, $p['columns']);
        $this->assertSame(['Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Jenis Pembayaran', 'Kode Akun', 'Akun Beban', 'Keterangan', 'Total Transaksi', 'Kode Lawan', 'Nama Akun Lawan', 'Nilai Lawan'], $p['columns']);
        $this->assertSame(['5203', '5209'], array_column($p['rows'], 'account_code'), 'newest first');

        $row = $p['rows'][0];
        $this->assertSame(1, $row['no']);
        $this->assertSame('April 2026', $row['month']);
        $this->assertSame('Pusat', $row['branch']);
        $this->assertSame('Utang Usaha', $row['payment_method']);
        $this->assertSame('Beban Listrik, Air & Internet', $row['account_name']);
        $this->assertSame(['2101', 'Hutang Usaha', '200000.00', '200000.00'], [$row['counter_code'], $row['counter_name'], $row['amount'], $row['counter_amount']]);

        $registered = $p['totals']['amount'];
        $journalDebits = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('e.source_kind', 'expense')->sum('l.debit');
        $this->assertSame(number_format((float) $journalDebits, 2, '.', ''), $registered, 'register total = journal debits');
    }

    public function test_filters_and_branch_scope(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->make(['description' => 'Parkir mobil', 'expense_date' => '2026-01-15']);
        $this->make(['description' => 'Listrik toko', 'expense_account_id' => $this->id('5203'), 'branch_id' => $other->id, 'expense_date' => '2026-06-10']);

        $count = fn (string $qs) => count($this->get("/transactions/expenses?{$qs}")->viewData('page')['props']['rows']);

        $this->assertSame(1, $count('search=parkir'));
        $this->assertSame(1, $count('search=5203'), 'account code');
        $this->assertSame(1, $count('search=Listrik%2C+Air'), 'account name');
        $this->assertSame(1, $count('search=BBN-2026-00002'), 'reference');
        $this->assertSame(1, $count("branch_id={$other->id}"));
        $this->assertSame(1, $count('from=2026-05-01&to=2026-12-31'));
        $this->assertSame(0, $count('search=tidak-ada'));
        $this->get('/transactions/expenses?from=bukan')->assertSessionHasErrors('from');

        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $other->id]));
        $this->assertSame(1, $count(''));
    }

    public function test_csv_export_follows_filters_and_neutralises_formulas(): void
    {
        $this->make(['description' => '=HYPERLINK("http://x")']);
        $this->make(['description' => 'Aman', 'expense_date' => '2026-08-01']);

        $csv = $this->get('/transactions/expenses/export?from=2026-03-01&to=2026-03-31')->assertOk()->streamedContent();
        $lines = array_values(array_filter(preg_split('/\r?\n/', substr($csv, 3))));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(2, $lines, 'header + the March expense');
        $this->assertCount(15, str_getcsv($lines[0]), '14 columns + Status');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_reversal_mirrors_the_journal_and_leaves_the_totals(): void
    {
        $expense = $this->make();
        $keep = $this->make(['amount' => '1000']);

        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Salah akun', 'posted_on' => '2026-03-06'])->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('reversed', $expense->status);
        $net = [];
        foreach ([$expense->journal_entry_id, $expense->reversal_journal_entry_id] as $id) {
            foreach (JournalEntry::with('lines')->find($id)->lines as $l) {
                $net[$l->account_id] = bcadd($net[$l->account_id] ?? '0', bcsub($l->debit, $l->credit, 2), 2);
            }
        }
        $this->assertSame([], array_filter($net, fn ($v) => bccomp($v, '0', 2) !== 0), 'every account nets to zero');
        $this->assertTrue(JournalEntry::whereKey($expense->journal_entry_id)->exists());

        $totals = $this->get('/transactions/expenses')->viewData('page')['props']['totals'];
        $this->assertSame(['1000.00', 1], [$totals['amount'], $totals['expenses']]);
        $this->assertSame('paid', $keep->fresh()->status, 'the other expense is untouched');
    }

    public function test_reversal_rules(): void
    {
        $expense = $this->make(['payment_method' => 'utang', 'amount' => '100000']);

        $this->post("/transactions/expenses/{$expense->id}/reverse", [])->assertSessionHasErrors('reason');
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2026-03-01'])->assertSessionHasErrors('expense');
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Salah', 'posted_on' => '2027-03-01'])->assertSessionHasErrors('expense');

        app(TransactionPostingService::class)->expensePayment([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'fiscal_period_id' => $this->period->id, 'expense_id' => $expense->id,
            'paid_on' => '2026-03-10', 'amount' => '40000', 'payable_account_id' => $this->id('2101'), 'cash_account_id' => $this->id('1101'), 'idempotency_key' => 'pay-1',
        ]);
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Batal'])->assertSessionHasErrors('expense');
        $this->assertSame('partial', $expense->fresh()->status);

        $clean = $this->make();
        $this->post("/transactions/expenses/{$clean->id}/reverse", ['reason' => 'Batal', 'posted_on' => '2026-03-05'])->assertSessionHasNoErrors();
        $this->post("/transactions/expenses/{$clean->id}/reverse", ['reason' => 'Lagi'])->assertSessionHasErrors('expense');
    }

    public function test_other_company_and_other_branch_cannot_touch_an_expense(): void
    {
        $expense = $this->make();

        $this->actingAs(User::factory()->create(['company_id' => Company::create(['name' => 'Other', 'code' => 'OTH'])->id]));
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Coba-coba'])->assertNotFound();
        $this->post("/transactions/expenses/{$expense->id}/attachments", ['attachments' => [UploadedFile::fake()->image('a.png')]])->assertNotFound();

        $b = $this->company->branches()->create(['name' => 'Cabang B', 'code' => 'CB']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $b->id]));
        $this->post("/transactions/expenses/{$expense->id}/reverse", ['reason' => 'Coba-coba'])->assertNotFound();
    }

    public function test_evidence_can_be_added_to_a_registered_expense(): void
    {
        $expense = $this->make();

        $this->post("/transactions/expenses/{$expense->id}/attachments", ['attachments' => [UploadedFile::fake()->image('a.png')]])->assertSessionHasNoErrors();

        $this->get('/transactions/expenses')->assertInertia(fn ($p) => $p->has('rows.0.attachments', 1)->where('maxAttachments', 5));
    }

    public function test_register_requires_a_company(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/transactions/expenses')->assertForbidden();
        $this->get('/transactions/expenses/create')->assertForbidden();
    }
}
