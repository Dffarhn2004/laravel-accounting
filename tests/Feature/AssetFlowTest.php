<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DepreciationPosting;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** PRD 03 #6, 04 #5-6, 05 #11 and ACC-08. Straight-line depreciation starts the month after acquisition. */
class AssetFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Aset Co', 'code' => 'AST']);
        $this->branchId = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST'])->id;
        $this->periodId = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id;
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    /** @param  array<string, mixed>  $override */
    private function buy(array $override = []): void
    {
        $this->post('/transactions/fixed-assets', array_replace([
            'branch_id' => $this->branchId,
            'acquired_on' => '2026-01-10',
            'payment_method' => 'cash',
            'asset_account_id' => $this->id('1505'),
            'description' => 'Laptop kasir',
            'cost' => '2400000',
            'idempotency_key' => (string) Str::uuid(),
        ], $override))->assertSessionHasNoErrors();
    }

    private function net(string $code): string
    {
        $row = DB::table('journal_lines')->where('account_id', $this->id($code))->first([
            DB::raw('COALESCE(SUM(debit),0) as d'), DB::raw('COALESCE(SUM(credit),0) as c'),
        ]);

        return bcsub(bcadd((string) $row->d, '0', 2), bcadd((string) $row->c, '0', 2), 2);
    }

    public function test_acc08_posts_the_following_months_in_order_and_is_idempotent(): void
    {
        $this->get('/transactions/fixed-assets')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/fixed-assets')
            ->where('nextNumber', 'AST-2026-00001')
            ->where('categories.4.code', '1505'));

        $key = (string) Str::uuid();
        $this->buy(['idempotency_key' => $key]);
        $this->buy(['idempotency_key' => $key, 'description' => 'Ulang']);
        $this->assertSame(1, FixedAsset::count());
        $asset = FixedAsset::firstOrFail();
        $this->assertSame('AST-2026-00001', $asset->code);
        $this->assertSame('50000.00', (string) $asset->schedule->amount);
        $this->assertSame('2026-02-01', $asset->depreciation_from->toDateString());

        $page = $this->get('/data/d-asset?search=AST-2026-00001')->assertOk()->viewData('page');
        $this->assertSame('accounting/data-asset', $page['component']);
        $this->assertCount(17, $page['props']['columns']);
        $this->assertSame('Cash', $page['props']['rows'][0]['payment']);
        $this->assertSame('4', $page['props']['rows'][0]['life_years']);
        $this->assertSame('50000.00', $page['props']['rows'][0]['monthly']);
        $this->assertSame('2030', $page['props']['rows'][0]['end_year']);
        $this->assertStringContainsString('Total Transaksi', $this->get('/data/d-asset/export')->assertOk()->streamedContent());

        $this->post("/transactions/fixed-assets/{$asset->id}/depreciate", ['period_month' => '2026-01'])->assertSessionHasErrors('asset');
        $this->post("/transactions/fixed-assets/{$asset->id}/depreciate", ['period_month' => '2026-03'])->assertSessionHasErrors('asset');

        $this->post('/data/simulasi', ['year' => 2026, 'month' => 3])->assertSessionHasNoErrors();
        $this->assertSame(2, DepreciationPosting::whereNull('reversed_at')->count());
        $this->assertSame('50000.00', (string) DepreciationPosting::where('period_month', '2026-02')->value('amount'));
        $this->assertSame('100000.00', $this->net('5305'));
        $this->assertSame('-100000.00', $this->net('1555'));
        $this->post('/data/simulasi', ['year' => 2026, 'month' => 3])->assertSessionHasNoErrors();
        $this->assertSame(2, DepreciationPosting::whereNull('reversed_at')->count());

        $board = $this->get('/data/simulasi?year=2026&month=4')->assertOk()->viewData('page')['props']['board'];
        $this->assertSame('accounting/simulasi', $this->get('/data/simulasi?year=2026&month=4')->viewData('page')['component']);
        $this->assertSame('0.00', $board['assets'][0]['cells'][0]['accumulated']);
        $this->assertSame('2400000.00', $board['assets'][0]['cells'][2]['cost']);
        $this->assertSame('100000.00', $board['assets'][0]['cells'][2]['accumulated']);
        $this->assertSame('50000.00', $board['pending'][0]['amount']);
        $this->assertSame('2026-04', $board['pending'][0]['month']);
        $this->assertSame('0.00', $board['reconciliation']['expense_difference']);
        $this->assertSame('0.00', $board['reconciliation']['accumulated_difference']);
    }

    public function test_a_credit_purchase_joins_payables_and_fifo_reduces_the_oldest(): void
    {
        $this->buy(['payment_method' => 'utang', 'cost' => '100000', 'acquired_on' => '2026-03-01', 'description' => 'Mesin utang', 'asset_account_id' => $this->id('1504')]);
        $this->post('/transactions/cash-bank', [
            'branch_id' => $this->branchId, 'posted_on' => '2026-03-20', 'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'), 'to_account_id' => $this->id('2101'),
            'from_amount' => '30000', 'to_amount' => '30000', 'description' => 'Bayar sebagian',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $row = collect($this->get('/laporan/lap-hutang?as_of=2026-03-31')->viewData('page')['props']['rows'])->firstWhere('source', 'Asset');
        $this->assertSame('70000.00', $row['remaining']);
        $this->assertSame('30000.00', $row['paid']);
        $this->assertStringContainsString('/data/d-asset?search=AST-', $row['url']);

        $asset = FixedAsset::firstOrFail();
        $this->post("/data/d-asset/{$asset->id}/reverse", ['reason' => 'Salah beli', 'posted_on' => '2026-03-21'])->assertSessionHasErrors('asset');
    }

    public function test_reversing_depreciation_then_the_purchase_restores_the_ledger(): void
    {
        $this->buy();
        $asset = FixedAsset::firstOrFail();
        $this->post('/data/simulasi', ['year' => 2026, 'month' => 3])->assertSessionHasNoErrors();
        $this->post("/data/d-asset/{$asset->id}/reverse", ['reason' => 'Masih tersusut', 'posted_on' => '2026-03-02'])->assertSessionHasErrors('asset');

        $this->post("/data/d-asset/{$asset->id}/reverse-depreciation", ['reason' => 'Salah bulan', 'posted_on' => '2026-03-02'])->assertSessionHasNoErrors();
        $this->assertSame('2026-02', DepreciationPosting::whereNull('reversed_at')->value('period_month'));
        $this->post("/data/d-asset/{$asset->id}/reverse-depreciation", ['reason' => 'Salah bulan juga', 'posted_on' => '2026-03-02'])->assertSessionHasNoErrors();
        $this->assertSame(0, DepreciationPosting::whereNull('reversed_at')->count());
        $this->post("/data/d-asset/{$asset->id}/reverse", ['reason' => 'Batal beli', 'posted_on' => '2026-03-02'])->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->net('1505'));
        $this->assertSame('0.00', $this->net('1101'));
        $this->assertSame('0.00', $this->net('5305'));
        $this->assertSame('0.00', $this->net('1555'));
        $this->assertSame(6, JournalEntry::count());
        $this->assertNotNull($asset->fresh()->reversed_at);
    }

    public function test_opening_cutoff_enters_the_register_without_a_second_journal(): void
    {
        $this->post('/inputan/modal', [
            'branch_id' => $this->branchId,
            'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-01-01',
            'items' => [
                ['account_id' => $this->id('1503'), 'amount' => '20000000', 'description' => 'Mobil', 'acquired_on' => '2024-01-01', 'accumulated' => '5000000'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, JournalEntry::count());
        $asset = FixedAsset::firstOrFail();
        $this->assertSame('opening', $asset->source);
        $this->assertSame('2026-02-01', $asset->depreciation_from->toDateString());
        $this->assertSame('5000000.00', (string) $asset->schedule->accumulated_amount);
        $this->assertSame('15000000.00', (string) $asset->schedule->book_value);
        $this->assertSame('20000000.00', $this->net('1503'));
        $this->assertSame('-5000000.00', $this->net('1553'));

        $report = $this->get('/laporan/lap-asset?as_of=2026-01-31&source=opening')->assertOk()->viewData('page')['props']['report'];
        $this->assertSame('Modal', $report['rows'][0]['source']);
        $this->assertSame('5000000.00', $report['rows'][0]['accumulated']);
        $this->assertSame('0.00', $report['control']['cost'][0]['difference']);
        $this->assertSame('0.00', $report['control']['accumulated'][0]['difference']);

        $this->post("/transactions/fixed-assets/{$asset->id}/depreciate", ['period_month' => '2026-02'])->assertSessionHasNoErrors();
        $opening = OpeningBalance::firstOrFail();
        $this->post("/data/d-modal/{$opening->id}/reverse", ['reason' => 'Masih tersusut', 'posted_on' => '2026-02-02'])->assertSessionHasErrors('modal');
        $this->post("/data/d-asset/{$asset->id}/reverse", ['reason' => 'Bukan menu ini', 'posted_on' => '2026-02-02'])->assertSessionHasErrors('asset');
        $this->post("/data/d-asset/{$asset->id}/reverse-depreciation", ['reason' => 'Balik dulu', 'posted_on' => '2026-02-02'])->assertSessionHasNoErrors();
        $this->post("/data/d-modal/{$opening->id}/reverse", ['reason' => 'Salah cut-off', 'posted_on' => '2026-02-02'])->assertSessionHasNoErrors();
        $this->assertNotNull($asset->fresh()->reversed_at);
        $this->assertSame('0.00', $this->net('1503'));
    }

    public function test_land_is_not_depreciated_and_a_manual_journal_shows_as_a_difference(): void
    {
        $this->buy(['asset_account_id' => $this->id('1501'), 'cost' => '1000000', 'description' => 'Tanah']);
        $asset = FixedAsset::firstOrFail();
        $this->assertSame(0, $asset->useful_life_months);
        $this->post("/transactions/fixed-assets/{$asset->id}/depreciate", ['period_month' => '2026-02'])->assertSessionHasErrors('asset');
        $this->post('/data/simulasi', ['year' => 2026, 'month' => 6])->assertSessionHasNoErrors();
        $this->assertSame(0, DepreciationPosting::count());
        $board = $this->get('/data/simulasi?year=2026&month=6')->viewData('page')['props']['board'];
        $this->assertSame([], $board['pending']);
        $this->assertSame('1000000.00', $board['assets'][0]['cells'][0]['book']);
        $this->assertSame('1000000.00', $board['assets'][0]['cells'][5]['book']);

        $this->buy(['description' => 'Lemari', 'cost' => '480000', 'acquired_on' => '2026-02-01']);
        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => $this->branchId, 'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-02-15', 'description' => 'Jurnal manual aset', 'idempotency_key' => 'manual-asset',
            'source_kind' => 'manual',
            'lines' => [
                ['account_id' => $this->id('1505'), 'debit' => '1000', 'credit' => '0'],
                ['account_id' => $this->id('1101'), 'debit' => '0', 'credit' => '1000'],
            ],
        ]);
        $control = collect($this->get('/laporan/lap-asset?as_of=2026-02-28&account_id='.$this->id('1505'))->viewData('page')['props']['report']['control']['cost']);
        $this->assertSame('1000.00', $control->first()['difference']);
    }

    public function test_another_company_and_another_branch_cannot_touch_the_asset(): void
    {
        $this->buy();
        $asset = FixedAsset::firstOrFail();
        $six = array_map(fn ($i) => UploadedFile::fake()->image("n{$i}.png"), range(1, 6));
        $this->post('/transactions/fixed-assets', [
            'branch_id' => $this->branchId, 'acquired_on' => '2026-01-11', 'payment_method' => 'cash',
            'asset_account_id' => $this->id('1505'), 'description' => 'Terlalu banyak', 'cost' => '100',
            'idempotency_key' => (string) Str::uuid(), 'attachments' => $six,
        ])->assertSessionHasErrors('attachments');
        $this->post("/data/d-asset/{$asset->id}/attachments", ['attachments' => [UploadedFile::fake()->image('nota.png')]])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->get('/data/d-asset')->viewData('page')['props']['rows'][0]['attachments']);

        $otherBranch = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBG']);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $otherBranch->id]));
        $this->post('/transactions/fixed-assets', [
            'branch_id' => $this->branchId, 'acquired_on' => '2026-01-12', 'payment_method' => 'cash',
            'asset_account_id' => $this->id('1505'), 'description' => 'Cabang lain', 'cost' => '100',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('branch_id');
        $this->get('/data/d-asset')->assertOk()->assertInertia(fn ($p) => $p->has('rows', 0));
        $this->post("/data/d-asset/{$asset->id}/reverse", ['reason' => 'Bukan cabang', 'posted_on' => '2026-01-12'])->assertNotFound();

        $foreign = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $branch = $foreign->branches()->create(['name' => 'Lain', 'code' => 'L']);
        $foreign->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($foreign);
        $this->actingAs(User::factory()->create(['company_id' => $foreign->id, 'branch_id' => $branch->id]));
        $this->post("/data/d-asset/{$asset->id}/reverse-depreciation", ['reason' => 'Bukan perusahaan', 'posted_on' => '2026-02-01'])->assertNotFound();
        $this->get('/laporan/lap-asset')->assertOk()->assertInertia(fn ($p) => $p->has('report.rows', 0));
    }
}
