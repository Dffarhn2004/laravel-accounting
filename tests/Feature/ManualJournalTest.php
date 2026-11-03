<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/** PRD 03 #10 Input Jurnal Manual and 04 #9 Data Jurnal Manual. */
class ManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Jurnal Co', 'code' => 'JRN']);
        $branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBG']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        $this->actingAs($this->user);
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    /** @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $override
     */
    private function payload(array $lines, array $override = []): array
    {
        return array_replace([
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => 99999,
            'posted_on' => '2026-04-02',
            'description' => 'Koreksi saldo',
            'idempotency_key' => (string) Str::uuid(),
            'lines' => $lines,
        ], $override);
    }

    private function balanced(): array
    {
        return [
            ['account_id' => $this->id('1101'), 'description' => 'Kas masuk', 'debit' => '150000', 'credit' => '0'],
            ['account_id' => $this->id('4101'), 'description' => '=pendapatan', 'debit' => '0', 'credit' => '150000'],
        ];
    }

    public function test_a_balanced_journal_is_numbered_and_ignores_the_browser_period(): void
    {
        $this->post('/accounting/manual-journal', $this->payload($this->balanced()))->assertSessionHasNoErrors()->assertRedirect('/data/d-jm');

        $entry = JournalEntry::where('source_kind', 'manual')->firstOrFail();
        $this->assertSame('JM-2026-00001', $entry->reference);
        $this->assertSame($this->period->id, $entry->fiscal_period_id);
        $this->assertSame('posted', $entry->status);
        $this->assertSame('Kas masuk', $entry->lines()->first()->description);
    }

    public function test_limits_balance_duplicate_accounts_and_a_foreign_branch_are_rejected(): void
    {
        $this->post('/accounting/manual-journal', $this->payload([
            ['account_id' => $this->id('1101'), 'debit' => '10', 'credit' => '0'],
        ]))->assertSessionHasErrors('lines');

        $seven = [];
        foreach (['1101', '1111', '1201', '1301', '1404', '1503', '2101'] as $i => $code) {
            $seven[] = ['account_id' => $this->id($code), 'debit' => $i < 6 ? '1' : '0', 'credit' => $i < 6 ? '0' : '6'];
        }
        $this->post('/accounting/manual-journal', $this->payload($seven))->assertSessionHasErrors('lines');

        $this->post('/accounting/manual-journal', $this->payload([
            ['account_id' => $this->id('1101'), 'debit' => '10', 'credit' => '0'],
            ['account_id' => $this->id('4101'), 'debit' => '0', 'credit' => '9'],
        ]))->assertSessionHasErrors('lines');

        $this->post('/accounting/manual-journal', $this->payload([
            ['account_id' => $this->id('1101'), 'debit' => '10', 'credit' => '0'],
            ['account_id' => $this->id('1101'), 'debit' => '0', 'credit' => '10'],
        ]))->assertSessionHasErrors('lines.1.account_id');

        $other = $this->company->branches()->where('code', 'CBG')->value('id');
        $this->post('/accounting/manual-journal', $this->payload($this->balanced(), ['branch_id' => $other]))->assertSessionHasErrors('branch_id');
        $this->assertSame(0, JournalEntry::where('source_kind', 'manual')->count());
    }

    public function test_the_same_key_returns_the_existing_journal_and_does_not_attach_again(): void
    {
        $key = (string) Str::uuid();
        $file = ['attachments' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')]];
        $this->post('/accounting/manual-journal', $this->payload($this->balanced(), ['idempotency_key' => $key]) + $file)->assertSessionHasNoErrors();
        $this->post('/accounting/manual-journal', $this->payload($this->balanced(), ['idempotency_key' => $key, 'description' => 'Ulang']) + $file)->assertSessionHasNoErrors();

        $this->assertSame(1, JournalEntry::where('source_kind', 'manual')->count());
        $this->assertSame(1, Attachment::count());
        $this->assertSame('Koreksi saldo', JournalEntry::where('source_kind', 'manual')->value('description'));
    }

    public function test_register_has_twelve_columns_csv_and_reverses_the_whole_journal(): void
    {
        $this->post('/accounting/manual-journal', $this->payload($this->balanced()))->assertSessionHasNoErrors();
        $entry = JournalEntry::where('source_kind', 'manual')->firstOrFail();

        $this->get('/data/d-jm')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/data-jurnal-manual')
            ->has('columns', 12)
            ->where('columns.11', 'Cek Balance')
            ->has('rows', 2)
            ->where('rows.0.number', 'JM-2026-00001')
            ->where('rows.0.balance', 'Seimbang')
            ->where('totals.count', 1)
            ->where('totals.debit', '150000.00'));

        $csv = $this->get('/data/d-jm/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Cek Balance', $csv);
        $this->assertStringContainsString('Seimbang', $csv);
        $this->assertStringContainsString("'=pendapatan", $csv);

        $this->post("/data/d-jm/{$entry->id}/reverse", ['reason' => 'Salah akun', 'posted_on' => '2026-04-03'])->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame('reversed', $entry->status);
        $this->assertNotNull(JournalEntry::where('reversal_of_id', $entry->id)->first());
        $this->assertSame(1, JournalEntry::where('source_kind', 'manual')->count());
        $this->get('/data/d-jm')->assertInertia(fn ($p) => $p->where('rows.0.status', 'reversed')->where('totals.count', 0)->where('totals.debit', '0.00'));
    }

    public function test_another_company_cannot_see_or_reverse_the_journal(): void
    {
        $this->post('/accounting/manual-journal', $this->payload($this->balanced()))->assertSessionHasNoErrors();
        $entry = JournalEntry::where('source_kind', 'manual')->firstOrFail();

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $this->actingAs(User::factory()->create(['company_id' => $other->id]));
        $this->get('/data/d-jm')->assertInertia(fn ($p) => $p->has('rows', 0));
        $this->post("/data/d-jm/{$entry->id}/reverse", ['reason' => 'Bukan milik kami'])->assertNotFound();
    }
}
