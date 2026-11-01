<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD 03-INPUTAN-TRANSAKSI.md #8 Saldo Neraca and #9 Setoran Modal Awal:
 * two distinct pages, mutually exclusive per branch, balanced, posted exactly once.
 */
class OpeningPositionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Open Co', 'code' => 'OPEN']);
        $this->branch = $this->company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]));
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    private function payload(array $items, ?Branch $branch = null): array
    {
        return [
            'branch_id' => ($branch ?? $this->branch)->id,
            'fiscal_period_id' => $this->period->id,
            'posted_on' => '2026-01-01',
            'items' => $items,
        ];
    }

    private function item(string $code, string $amount, array $extra = []): array
    {
        return ['account_id' => $this->id($code), 'amount' => $amount] + $extra;
    }

    /** @return array{debit:string, credit:string, byCode: array<string, string>} signed (debit - credit) per account code */
    private function journal(OpeningBalance $opening): array
    {
        $entry = JournalEntry::with('lines.account')->findOrFail($opening->journal_entry_id);
        $debit = $credit = '0.00';
        $by = [];
        foreach ($entry->lines as $line) {
            $debit = bcadd($debit, $line->debit, 2);
            $credit = bcadd($credit, $line->credit, 2);
            $by[$line->account->code] = bcsub($line->debit, $line->credit, 2);
        }

        return ['debit' => $debit, 'credit' => $credit, 'byCode' => $by];
    }

    // ---- Saldo Neraca ------------------------------------------------------------

    public function test_balanced_saldo_neraca_posts_one_balanced_journal_with_audit_lines(): void
    {
        $this->post('/inputan/saldo', $this->payload([
            $this->item('1101', '1000000'),
            $this->item('2101', '300000'),
            $this->item('3101', '700000'),
            ['account_id' => $this->id('1111'), 'amount' => ''], // blank row ignored
        ]))->assertSessionHasNoErrors();

        $opening = OpeningBalance::firstOrFail();
        $this->assertSame('saldo_neraca', $opening->method);
        $this->assertSame(3, $opening->lines()->count());

        $j = $this->journal($opening);
        $this->assertSame($j['debit'], $j['credit']);
        $this->assertSame('1000000.00', $j['debit']);
        $this->assertSame(['1101' => '1000000.00', '2101' => '-300000.00', '3101' => '-700000.00'], $j['byCode']);
    }

    public function test_contra_accounts_reduce_their_group_and_post_on_the_opposite_side(): void
    {
        $this->post('/inputan/saldo', $this->payload([
            $this->item('1503', '10000000'),
            $this->item('1553', '2000000'),   // accumulated depreciation, contra asset
            $this->item('3101', '10000000'),
            $this->item('3201', '2000000'),   // prive, contra equity
        ]))->assertSessionHasNoErrors();

        $j = $this->journal(OpeningBalance::firstOrFail());
        $this->assertSame('-2000000.00', $j['byCode']['1553']);
        $this->assertSame('2000000.00', $j['byCode']['3201']);
        $this->assertSame($j['debit'], $j['credit']);
    }

    public function test_decimals_are_exact(): void
    {
        $this->post('/inputan/saldo', $this->payload([
            $this->item('1101', '0.10'), $this->item('1111', '0.20'), $this->item('3101', '0.30'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame('0.30', $this->journal(OpeningBalance::firstOrFail())['debit']);
    }

    public function test_unbalanced_saldo_is_rejected_and_nothing_is_written(): void
    {
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '1000000'), $this->item('3101', '999999.99')]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, OpeningBalance::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_saldo_rejects_income_expense_duplicates_and_bad_amounts(): void
    {
        $this->post('/inputan/saldo', $this->payload([$this->item('4101', '5'), $this->item('1101', '5')]))->assertSessionHasErrors('items.0.account_id');
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '5'), $this->item('1101', '5'), $this->item('3101', '10')]))->assertSessionHasErrors('items');
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '1.234'), $this->item('3101', '1')]))->assertSessionHasErrors('items.0.amount');
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '-5'), $this->item('3101', '5')]))->assertSessionHasErrors('items.0.amount');

        $this->assertSame(0, OpeningBalance::count());
    }

    public function test_date_must_be_inside_an_open_period(): void
    {
        $payload = $this->payload([$this->item('1101', '5'), $this->item('3101', '5')]);

        $this->post('/inputan/saldo', ['posted_on' => '2025-12-31'] + $payload)->assertSessionHasErrors('posted_on');

        $this->period->update(['status' => 'closed']);
        $this->post('/inputan/saldo', $payload)->assertSessionHasErrors('fiscal_period_id');
        $this->assertSame(0, OpeningBalance::count());
    }

    // ---- Setoran Modal Awal ------------------------------------------------------

    public function test_modal_awal_balances_with_owner_capital_as_the_difference(): void
    {
        $this->post('/inputan/modal', $this->payload([
            $this->item('1101', '5000000', ['description' => 'Kas toko']),
            $this->item('1301', '2000000'),
            $this->item('2101', '1000000'),
        ]))->assertSessionHasNoErrors();

        $opening = OpeningBalance::firstOrFail();
        $this->assertSame('modal_awal', $opening->method);

        $j = $this->journal($opening);
        $this->assertSame($j['debit'], $j['credit']);
        $this->assertSame('-6000000.00', $j['byCode']['3102'], 'capital = assets 7m - debts 1m');
        $this->assertSame('-1000000.00', $j['byCode']['2101']);
        $this->assertSame(4, $opening->lines()->count(), '3 items + capital line');
    }

    public function test_debts_above_assets_post_negative_capital_as_a_debit(): void
    {
        $this->post('/inputan/modal', $this->payload([$this->item('1101', '1000000'), $this->item('2501', '3000000')]))->assertSessionHasNoErrors();

        $this->assertSame('2000000.00', $this->journal(OpeningBalance::firstOrFail())['byCode']['3102']);
    }

    public function test_fixed_asset_cutoff_keeps_acquisition_info_and_books_accumulated_depreciation(): void
    {
        $this->post('/inputan/modal', $this->payload([
            $this->item('1503', '20000000', ['description' => 'Mobil box', 'acquired_on' => '2024-01-01', 'accumulated' => '5000000']),
        ]))->assertSessionHasNoErrors();

        $opening = OpeningBalance::firstOrFail();
        $j = $this->journal($opening);
        $this->assertSame('20000000.00', $j['byCode']['1503']);
        $this->assertSame('-5000000.00', $j['byCode']['1553']);
        $this->assertSame('-15000000.00', $j['byCode']['3102']);

        $meta = $opening->lines()->where('account_id', $this->id('1503'))->firstOrFail()->meta;
        $this->assertSame('2024-01-01', $meta['acquired_on']);
        $this->assertSame(8, $meta['useful_life_years']);
        $this->assertSame('5000000.00', $meta['accumulated']);
    }

    public function test_fixed_asset_rows_are_validated(): void
    {
        $this->post('/inputan/modal', $this->payload([$this->item('1503', '100')]))->assertSessionHasErrors('items.0.acquired_on');
        $this->post('/inputan/modal', $this->payload([$this->item('1503', '100', ['acquired_on' => '2027-01-01'])]))->assertSessionHasErrors('items.0.acquired_on');
        $this->post('/inputan/modal', $this->payload([$this->item('1503', '100', ['acquired_on' => '2024-01-01', 'accumulated' => '101'])]))->assertSessionHasErrors('items.0.accumulated');

        $this->assertSame(0, OpeningBalance::count());
    }

    public function test_modal_awal_only_accepts_accounts_allowed_by_the_template(): void
    {
        $this->company->templateOptions()->where('group', 'opening_account')->where('code', '1101')->update(['is_active' => false]);

        $this->post('/inputan/modal', $this->payload([$this->item('1101', '100')]))->assertSessionHasErrors('items.0.account_id');
        $this->post('/inputan/modal', $this->payload([$this->item('3101', '100')]))->assertSessionHasErrors('items.0.account_id');
        $this->assertSame(0, OpeningBalance::count());
    }

    // ---- Exclusivity and exactly-once --------------------------------------------

    public function test_the_two_methods_are_mutually_exclusive_per_branch_and_period(): void
    {
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '10'), $this->item('3101', '10')]))->assertSessionHasNoErrors();

        $this->post('/inputan/modal', $this->payload([$this->item('1101', '10')]))->assertSessionHasErrors('branch_id');
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '10'), $this->item('3101', '10')]))->assertSessionHasErrors('branch_id');

        $this->assertSame(1, OpeningBalance::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_modal_first_blocks_saldo_and_another_branch_stays_free(): void
    {
        $this->post('/inputan/modal', $this->payload([$this->item('1101', '10')]))->assertSessionHasNoErrors();
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '10'), $this->item('3101', '10')]))->assertSessionHasErrors('branch_id');

        $other = $this->company->branches()->create(['name' => 'Cabang 2', 'code' => 'C2']);
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '10'), $this->item('3101', '10')], $other))->assertSessionHasNoErrors();
        $this->assertSame(2, OpeningBalance::count());
    }

    public function test_legacy_generic_opening_page_respects_the_same_exclusivity(): void
    {
        $this->post('/inputan/saldo', $this->payload([$this->item('1101', '10'), $this->item('3101', '10')]))->assertSessionHasNoErrors();

        $this->post('/accounting/opening-balances', [
            'branch_id' => $this->branch->id, 'fiscal_period_id' => $this->period->id, 'posted_on' => '2026-01-01',
            'description' => 'Legacy', 'idempotency_key' => 'legacy-1',
            'lines' => [['account_id' => $this->id('1101'), 'debit' => '5', 'credit' => '0'], ['account_id' => $this->id('3101'), 'debit' => '0', 'credit' => '5']],
        ])->assertSessionHasErrors('branch_id');

        $this->assertSame(1, OpeningBalance::count());
    }

    // ---- Pages and isolation -----------------------------------------------------

    public function test_pages_render_and_require_a_company(): void
    {
        $this->get('/inputan/saldo')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/saldo-neraca')->has('accounts')->has('branches')->has('openings'));
        $this->get('/inputan/modal')->assertOk()->assertInertia(fn ($p) => $p->component('accounting/modal-awal')->has('assetClasses')->has('recorded'));

        $this->actingAs(User::factory()->create());
        $this->get('/inputan/saldo')->assertForbidden();
        $this->get('/inputan/modal')->assertForbidden();
    }

    public function test_foreign_branch_and_accounts_are_rejected(): void
    {
        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $foreignBranch = $other->branches()->create(['name' => 'X', 'code' => 'X']);
        $foreignAccount = $other->accounts()->create(['code' => '1101', 'name' => 'Kas', 'type' => 'asset']);

        $this->post('/inputan/saldo', ['branch_id' => $foreignBranch->id] + $this->payload([$this->item('1101', '10'), $this->item('3101', '10')]))->assertSessionHasErrors('branch_id');
        $this->post('/inputan/saldo', $this->payload([['account_id' => $foreignAccount->id, 'amount' => '10'], $this->item('3101', '10')]))->assertSessionHasErrors('items.0.account_id');
        $this->assertSame(0, OpeningBalance::count());
    }
}
