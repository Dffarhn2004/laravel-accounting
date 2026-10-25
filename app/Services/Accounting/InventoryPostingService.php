<?php

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\InventoryCostLayer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryPostingService
{
    public function purchase(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $item = InventoryItem::where('company_id', $data['company_id'])
                ->findOrFail($data['inventory_item_id']);
            $this->unique($data);
            $layer = InventoryCostLayer::lockForUpdate()->firstOrCreate([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'inventory_item_id' => $item->id,
            ]);
            $layer->refresh();
            $quantity = (string) $data['quantity'];
            $total = bcmul($quantity, (string) $data['unit_cost'], 2);
            $movement = InventoryMovement::create([
                ...$data,
                'movement_type' => 'purchase',
                'total_cost' => $total,
                'quantity_balance' => bcadd((string) $layer->quantity, $quantity, 4),
                'value_balance' => bcadd((string) $layer->value, $total, 2),
            ]);
            $layer->update([
                'quantity' => $movement->quantity_balance,
                'value' => $movement->value_balance,
            ]);
            $this->post($data, $movement->id, [
                ['account_id' => $item->inventory_account_id, 'debit' => $total, 'credit' => '0'],
                ['account_id' => $data['settlement_account_id'], 'debit' => '0', 'credit' => $total],
            ]);

            return $movement->load('journalEntry');
        });
    }

    public function issue(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $item = InventoryItem::where('company_id', $data['company_id'])
                ->findOrFail($data['inventory_item_id']);
            $this->unique($data);
            $layer = InventoryCostLayer::where([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'inventory_item_id' => $item->id,
            ])->lockForUpdate()->first();
            $quantity = (string) $data['quantity'];
            if (! $layer || bccomp((string) $layer->quantity, $quantity, 4) < 0) {
                throw new InvalidArgumentException('Insufficient inventory stock.');
            }
            $average = bcdiv((string) $layer->value, (string) $layer->quantity, 8);
            $total = bcmul($quantity, $average, 2);
            $movement = InventoryMovement::create([
                ...$data,
                'movement_type' => 'issue',
                'unit_cost' => $average,
                'total_cost' => $total,
                'quantity_balance' => bcsub((string) $layer->quantity, $quantity, 4),
                'value_balance' => bcsub((string) $layer->value, $total, 2),
            ]);
            $layer->update([
                'quantity' => $movement->quantity_balance,
                'value' => $movement->value_balance,
            ]);
            $this->post($data, $movement->id, [
                ['account_id' => $item->cogs_account_id, 'debit' => $total, 'credit' => '0'],
                ['account_id' => $item->inventory_account_id, 'debit' => '0', 'credit' => $total],
            ]);

            return $movement->load('journalEntry');
        });
    }

    private function post(array $data, int $sourceId, array $lines): void
    {
        app(JournalPostingService::class)->post([
            ...$data,
            'posted_on' => $data['moved_on'],
            'description' => $data['description'] ?? 'Inventory movement',
            'source_type' => self::class,
            'source_id' => (string) $sourceId,
            'lines' => $lines,
        ]);
    }

    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])
            ->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
        }
    }

    private function unique(array $data): void
    {
        if (InventoryMovement::where('company_id', $data['company_id'])
            ->where('idempotency_key', $data['idempotency_key'])->exists()) {
            throw new InvalidArgumentException('Duplicate inventory movement.');
        }
    }
}
