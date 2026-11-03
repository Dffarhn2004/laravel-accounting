<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\InventoryReportService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PayableReportService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PRD 03 #4, 04 #3 and 05 #7. Moving average per branch; a backdated movement is rejected.
 */
class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Stok Co', 'code' => 'STK']);
        $this->branchId = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST'])->id;
        $this->periodId = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id;
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
        $this->post('/system/template/inventory', [
            'sku' => 'BJ', 'name' => 'Biji kopi', 'unit' => 'kg', 'minimum_stock' => '5',
            'inventory_account_id' => $this->id('1301'), 'cogs_account_id' => $this->id('5101'),
        ])->assertSessionHasNoErrors();
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    private function itemId(): int
    {
        return (int) $this->company->inventoryItems()->value('id');
    }

    /** @param  array<string, mixed>  $override */
    private function buy(array $override = []): void
    {
        $this->post('/inputan/persediaan', array_replace([
            'branch_id' => $this->branchId,
            'moved_on' => '2026-03-02',
            'kind' => 'purchase',
            'payment_method' => 'cash',
            'inventory_item_id' => $this->itemId(),
            'description' => 'Beli kopi',
            'quantity' => '10',
            'unit_cost' => '10000',
            'freight' => '1000',
            'tax' => '500',
            'idempotency_key' => (string) Str::uuid(),
        ], $override))->assertSessionHasNoErrors();
    }

    public function test_freight_and_tax_raise_the_average_and_a_backdate_is_rejected(): void
    {
        $this->buy();
        $this->buy(['unit_cost' => '20000', 'freight' => '0', 'tax' => '0', 'description' => 'Beli lagi']);
        $this->post('/inputan/persediaan', [
            'branch_id' => $this->branchId, 'moved_on' => '2026-03-01', 'kind' => 'purchase', 'payment_method' => 'cash',
            'inventory_item_id' => $this->itemId(), 'description' => 'Mundur', 'quantity' => '1', 'unit_cost' => '1000',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('inventory');

        $this->post('/inputan/persediaan', [
            'branch_id' => $this->branchId, 'moved_on' => '2026-03-03', 'kind' => 'issue',
            'inventory_item_id' => $this->itemId(), 'description' => 'Pakai', 'quantity' => '5',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $issue = InventoryMovement::where('movement_type', 'issue')->firstOrFail();
        $this->assertSame('15075.00', (string) $issue->unit_cost);
        $this->assertSame('75375.00', (string) $issue->total_cost);
        $this->assertSame('15.0000', (string) $issue->quantity_balance);

        $this->post('/inputan/persediaan', [
            'branch_id' => $this->branchId, 'moved_on' => '2026-03-04', 'kind' => 'issue',
            'inventory_item_id' => $this->itemId(), 'description' => 'Habis', 'quantity' => '16',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('inventory');
        $this->post('/inputan/persediaan', [
            'branch_id' => $this->branchId, 'moved_on' => '2026-03-04', 'kind' => 'issue',
            'inventory_item_id' => $this->itemId(), 'description' => 'Sisa tipis', 'quantity' => '11',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $page = $this->get('/accounting/inventory?year=2026&month=3')->assertOk()->viewData('page')['props'];
        $row = collect($page['rows'])->firstWhere('item_id', $this->itemId());
        $this->assertSame('Di bawah minimum', $row['status']);
        $this->assertSame('4.0000', $row['end_qty']);
        $this->assertSame('60300.00', $row['end_value']);
        $this->assertSame('60300.00', $page['reconciliation']['documents']);
        $this->assertSame('60300.00', $page['reconciliation']['ledger']);
        $this->assertSame('0.00', $page['reconciliation']['difference']);

        $card = $this->get('/accounting/inventory?year=2026&month=3&item_id='.$this->itemId())->viewData('page')['props']['card'];
        $this->assertSame('Pemakaian', end($card)['kind']);
        $this->assertSame('4.0000', end($card)['qty']);
    }

    public function test_only_the_latest_movement_reverses_and_restores_stock_and_the_ledger(): void
    {
        $this->buy();
        $this->buy(['unit_cost' => '20000', 'freight' => '0', 'tax' => '0']);
        $this->post('/inputan/persediaan', [
            'branch_id' => $this->branchId, 'moved_on' => '2026-03-03', 'kind' => 'issue',
            'inventory_item_id' => $this->itemId(), 'description' => 'Pakai', 'quantity' => '5',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $first = InventoryMovement::orderBy('id')->firstOrFail();
        $issue = InventoryMovement::where('movement_type', 'issue')->firstOrFail();
        $this->post("/data/d-persediaan/{$first->id}/reverse", ['reason' => 'Salah'])->assertSessionHasErrors('inventory');
        $this->post("/data/d-persediaan/{$issue->id}/reverse", ['reason' => 'Salah pakai'])->assertSessionHasNoErrors();

        $layer = InventoryCostLayer::firstOrFail();
        $this->assertSame('20.0000', bcadd((string) $layer->quantity, '0', 4));
        $this->assertSame('301500.00', bcadd((string) $layer->value, '0', 2));
        $this->assertNotNull($issue->fresh()->reversed_at);
        $this->assertSame(4, JournalEntry::count(), 'the issue journal stays and a reversal is added');

        $report = app(InventoryReportService::class)->build([
            'company_id' => $this->company->id, 'branch_id' => $this->branchId, 'year' => 2026, 'month' => 3,
        ]);
        $this->assertSame('226125.00', $report['reconciliation']['documents']);
        $this->assertSame('0.00', $report['reconciliation']['difference']);

        $after = app(InventoryReportService::class)->build([
            'company_id' => $this->company->id, 'branch_id' => $this->branchId, 'year' => 2026, 'month' => 11,
        ]);
        $this->assertSame('301500.00', $after['reconciliation']['documents']);
        $this->assertSame('0.00', $after['reconciliation']['difference']);
    }

    public function test_a_credit_purchase_joins_the_payable_fifo_and_another_company_cannot_see_it(): void
    {
        $this->post('/inputan/saldo', [
            'branch_id' => $this->branchId, 'fiscal_period_id' => $this->periodId, 'posted_on' => '2026-01-02',
            'items' => [
                ['account_id' => $this->id('1101'), 'amount' => '100000'],
                ['account_id' => $this->id('3101'), 'amount' => '100000'],
            ],
        ])->assertSessionHasNoErrors();
        $this->buy(['payment_method' => 'utang', 'quantity' => '4', 'unit_cost' => '10000', 'freight' => '0', 'tax' => '0', 'moved_on' => '2026-02-01', 'description' => 'Beli utang']);
        $this->post('/transactions/cash-bank', [
            'branch_id' => $this->branchId, 'posted_on' => '2026-02-10', 'transaction_type' => 'bayar_hutang',
            'from_account_id' => $this->id('1101'), 'to_account_id' => $this->id('2101'),
            'from_amount' => '15000', 'to_amount' => '15000', 'description' => 'Cicil',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $row = collect(app(PayableReportService::class)->build([
            'company_id' => $this->company->id, 'as_of' => '2026-03-01', 'status' => 'open',
        ])['rows'])->firstWhere('source', 'Persediaan');
        $this->assertSame('25000.00', $row['remaining']);
        $this->assertSame('15000.00', $row['paid']);

        $purchase = InventoryMovement::where('payment_method', 'utang')->firstOrFail();
        $this->post("/data/d-persediaan/{$purchase->id}/reverse", ['reason' => 'Masih ada bayar'])->assertSessionHasErrors('inventory');

        $register = $this->get('/data/d-persediaan')->assertOk()->viewData('page')['props'];
        $this->assertCount(19, $register['columns']);
        $this->assertSame('PRS-2026-00001', $register['rows'][0]['reference']);

        app(JournalPostingService::class)->post([
            'company_id' => $this->company->id, 'branch_id' => $this->branchId, 'fiscal_period_id' => $this->periodId,
            'posted_on' => '2026-02-15', 'description' => 'Jurnal stok', 'idempotency_key' => 'stk-gap',
            'source_kind' => 'manual', 'lines' => [
                ['account_id' => $this->id('1301'), 'debit' => '1000', 'credit' => '0'],
                ['account_id' => $this->id('3101'), 'debit' => '0', 'credit' => '1000'],
            ],
        ]);
        $report = app(InventoryReportService::class)->build([
            'company_id' => $this->company->id, 'branch_id' => $this->branchId, 'year' => 2026, 'month' => 2,
        ]);
        $this->assertSame('40000.00', $report['reconciliation']['documents']);
        $this->assertSame('1000.00', $report['reconciliation']['difference']);

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $branch = $other->branches()->create(['name' => 'Lain', 'code' => 'L']);
        $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        $this->actingAs(User::factory()->create(['company_id' => $other->id, 'branch_id' => $branch->id]));
        $this->assertSame([], $this->get('/data/d-persediaan')->viewData('page')['props']['rows']);
    }

    public function test_a_branch_bound_user_cannot_post_or_see_another_branch(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBR']);
        $this->buy(['branch_id' => $other->id]);
        $bound = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branchId]);
        $this->actingAs($bound);

        $this->assertSame([], $this->get('/data/d-persediaan')->viewData('page')['props']['rows']);
        $this->post('/inputan/persediaan', [
            'branch_id' => $other->id, 'moved_on' => '2026-03-02', 'kind' => 'purchase', 'payment_method' => 'cash',
            'inventory_item_id' => $this->itemId(), 'description' => 'Cabang lain', 'quantity' => '1', 'unit_cost' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('branch_id');
    }
}
