<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\InventoryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_average_costs_issue_and_rejects_over_issue(): void
    {
        [$company, $branch, $period, $item, $inventory, $cogs, $cash] = $this->foundation();
        $service = app(InventoryPostingService::class);
        $base = compact('company', 'branch', 'period', 'item');

        $service->purchase([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'inventory_item_id' => $item->id, 'quantity' => '10', 'unit_cost' => '10000',
            'settlement_account_id' => $cash->id, 'moved_on' => '2026-01-01', 'idempotency_key' => 'buy-1',
        ]);
        $service->purchase([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'inventory_item_id' => $item->id, 'quantity' => '10', 'unit_cost' => '20000',
            'settlement_account_id' => $cash->id, 'moved_on' => '2026-01-02', 'idempotency_key' => 'buy-2',
        ]);
        $issue = $service->issue([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'inventory_item_id' => $item->id, 'quantity' => '5', 'moved_on' => '2026-01-03', 'idempotency_key' => 'issue-1',
        ]);

        $this->assertSame('15000.00', $issue->unit_cost);
        $this->assertSame('15.0000', $issue->quantity_balance);
        $this->assertSame('225000.00', $issue->value_balance);

        $this->expectException(InvalidArgumentException::class);
        $service->issue([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'inventory_item_id' => $item->id, 'quantity' => '16', 'moved_on' => '2026-01-04', 'idempotency_key' => 'issue-2',
        ]);
    }

    private function foundation(): array
    {
        $company = Company::create(['name' => 'Inventory Co', 'code' => 'INV-TEST']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $period = $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $inventory = $company->accounts()->create(['code' => '1300', 'name' => 'Inventory', 'type' => 'asset']);
        $cogs = $company->accounts()->create(['code' => '5100', 'name' => 'COGS', 'type' => 'expense']);
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $item = $company->inventoryItems()->create([
            'sku' => 'SKU-1', 'name' => 'Product', 'inventory_account_id' => $inventory->id,
            'cogs_account_id' => $cogs->id, 'minimum_stock' => '2',
        ]);
        $this->actingAs(User::factory()->create());

        return [$company, $branch, $period, $item, $inventory, $cogs, $cash];
    }
}
