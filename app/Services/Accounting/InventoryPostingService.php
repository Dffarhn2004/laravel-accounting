<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\InventoryCostLayer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inventory purchases and issues (PRD 03 #4). Moving average is per branch and item.
 * Freight and tax are added to the stock value. A movement dated before a later live
 * movement on the same branch and item is rejected; posted journals are never rewritten.
 */
class InventoryPostingService
{
    public function nextReference(int $companyId, string $movedOn): string
    {
        $prefix = 'PRS-'.substr($movedOn, 0, 4).'-';
        $last = InventoryMovement::where('company_id', $companyId)->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, inventory_item_id:int, quantity:string, unit_cost:string, settlement_account_id:int, moved_on:string, idempotency_key:string, description?:?string, freight?:string, tax?:string, payment_method?:?string, created_by?:int}  $data
     */
    public function purchase(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $existing = $this->existing($data);
            if ($existing) {
                return $existing;
            }
            $this->scope($data);
            $item = InventoryItem::where('company_id', $data['company_id'])->where('is_active', true)->findOrFail($data['inventory_item_id']);
            $settlement = Account::where('company_id', $data['company_id'])->where('is_active', true)->findOrFail($data['settlement_account_id']);
            if (! in_array($settlement->type, ['asset', 'liability'], true)) {
                throw new InvalidArgumentException('Akun lawan pembelian harus kas, bank, atau hutang.');
            }
            $this->guardDate($data);
            $layer = $this->lockLayer($data, $item, true);
            $quantity = $this->quantity($data['quantity']);
            $unit = bcadd((string) $data['unit_cost'], '0', 2);
            $freight = bcadd((string) ($data['freight'] ?? '0'), '0', 2);
            $tax = bcadd((string) ($data['tax'] ?? '0'), '0', 2);
            if (bccomp($unit, '0', 2) < 0 || bccomp($freight, '0', 2) < 0 || bccomp($tax, '0', 2) < 0) {
                throw new InvalidArgumentException('Harga, ongkir, dan pajak tidak boleh negatif.');
            }
            $total = bcadd(bcadd(bcmul($quantity, $unit, 2), $freight, 2), $tax, 2);
            if (bccomp($total, '0', 2) <= 0) {
                throw new InvalidArgumentException('Total pembelian persediaan harus lebih dari nol.');
            }
            $reference = $this->nextReferenceLocked($data['company_id'], $data['moved_on']);
            $movement = InventoryMovement::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'inventory_item_id' => $item->id,
                'settlement_account_id' => $settlement->id,
                'reference' => $reference,
                'movement_type' => 'purchase',
                'payment_method' => $data['payment_method'] ?? null,
                'quantity' => $quantity,
                'unit_cost' => $unit,
                'freight' => $freight,
                'tax' => $tax,
                'total_cost' => $total,
                'quantity_balance' => bcadd((string) $layer->quantity, $quantity, 4),
                'value_balance' => bcadd((string) $layer->value, $total, 2),
                'moved_on' => $data['moved_on'],
                'description' => $data['description'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $layer->update(['quantity' => $movement->quantity_balance, 'value' => $movement->value_balance]);
            $entry = $this->post($data, $movement, [
                ['account_id' => $item->inventory_account_id, 'debit' => $total, 'credit' => '0'],
                ['account_id' => $settlement->id, 'debit' => '0', 'credit' => $total],
            ], $reference);
            $movement->update(['journal_entry_id' => $entry->id]);

            return $movement->load('journalEntry');
        });
    }

    /**
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, inventory_item_id:int, quantity:string, moved_on:string, idempotency_key:string, description?:?string, created_by?:int}  $data
     */
    public function issue(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $existing = $this->existing($data);
            if ($existing) {
                return $existing;
            }
            $this->scope($data);
            $item = InventoryItem::where('company_id', $data['company_id'])->where('is_active', true)->findOrFail($data['inventory_item_id']);
            $this->guardDate($data);
            $layer = $this->lockLayer($data, $item, false);
            $quantity = $this->quantity($data['quantity']);
            if (! $layer || bccomp((string) $layer->quantity, $quantity, 4) < 0) {
                throw new InvalidArgumentException('Stok tidak cukup untuk pemakaian ini.');
            }
            $average = bcdiv((string) $layer->value, (string) $layer->quantity, 8);
            $total = bccomp($quantity, (string) $layer->quantity, 4) === 0
                ? bcadd((string) $layer->value, '0', 2)
                : bcmul($quantity, $average, 2);
            if (bccomp($total, (string) $layer->value, 2) > 0) {
                $total = bcadd((string) $layer->value, '0', 2);
            }
            if (bccomp($total, '0', 2) <= 0) {
                throw new InvalidArgumentException('Nilai pemakaian persediaan harus lebih dari nol.');
            }
            $reference = $this->nextReferenceLocked($data['company_id'], $data['moved_on']);
            $movement = InventoryMovement::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'inventory_item_id' => $item->id,
                'settlement_account_id' => null,
                'reference' => $reference,
                'movement_type' => 'issue',
                'payment_method' => 'pemakaian',
                'quantity' => $quantity,
                'unit_cost' => bcadd($average, '0', 2),
                'freight' => '0.00',
                'tax' => '0.00',
                'total_cost' => $total,
                'quantity_balance' => bcsub((string) $layer->quantity, $quantity, 4),
                'value_balance' => bcsub((string) $layer->value, $total, 2),
                'moved_on' => $data['moved_on'],
                'description' => $data['description'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $layer->update(['quantity' => $movement->quantity_balance, 'value' => $movement->value_balance]);
            $entry = $this->post($data, $movement, [
                ['account_id' => $item->cogs_account_id, 'debit' => $total, 'credit' => '0'],
                ['account_id' => $item->inventory_account_id, 'debit' => '0', 'credit' => $total],
            ], $reference);
            $movement->update(['journal_entry_id' => $entry->id]);

            return $movement->load('journalEntry');
        });
    }

    /**
     * Reverse only the latest live movement of this item and branch. The layer returns to the
     * previous live balance. A credit purchase cannot be reversed while a later debt payment exists.
     *
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverse(InventoryMovement $movement, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $data) {
            $movement = InventoryMovement::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($movement->id);
            if ($movement->reversed_at) {
                throw new InvalidArgumentException('Gerakan persediaan sudah dibalik.');
            }
            $latestId = InventoryMovement::where([
                'company_id' => $movement->company_id,
                'branch_id' => $movement->branch_id,
                'inventory_item_id' => $movement->inventory_item_id,
            ])->whereNull('reversed_at')->orderByDesc('moved_on')->orderByDesc('id')->value('id');
            if ((int) $latestId !== $movement->id) {
                throw new InvalidArgumentException('Hanya gerakan terakhir pada jenis dan cabang ini yang dapat dibalik.');
            }
            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $movement->moved_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal gerakan.');
            }
            $period = FiscalPeriod::where('company_id', $data['company_id'])->where('status', 'open')
                ->whereDate('starts_on', '<=', $postedOn)->whereDate('ends_on', '>=', $postedOn)->first();
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }
            if ($movement->movement_type === 'purchase' && $movement->settlement_account_id) {
                $settlement = Account::find($movement->settlement_account_id);
                if ($settlement?->type === 'liability' && TreasuryTransaction::where('company_id', $movement->company_id)
                    ->where('branch_id', $movement->branch_id)
                    ->where('transaction_type', 'bayar_hutang')
                    ->where('to_account_id', $settlement->id)
                    ->whereNull('reversed_at')
                    ->whereDate('posted_on', '>=', $movement->moved_on->toDateString())
                    ->exists()) {
                    throw new InvalidArgumentException('Masih ada pembayaran hutang yang lebih baru pada akun ini. Balik pembayaran itu terlebih dahulu.');
                }
            }

            $previous = InventoryMovement::where([
                'company_id' => $movement->company_id,
                'branch_id' => $movement->branch_id,
                'inventory_item_id' => $movement->inventory_item_id,
            ])->whereNull('reversed_at')->where('id', '!=', $movement->id)
                ->orderByDesc('moved_on')->orderByDesc('id')->first();
            $layer = InventoryCostLayer::where([
                'company_id' => $movement->company_id,
                'branch_id' => $movement->branch_id,
                'inventory_item_id' => $movement->inventory_item_id,
            ])->lockForUpdate()->first();
            if ($layer) {
                $layer->update([
                    'quantity' => $previous?->quantity_balance ?? '0.0000',
                    'value' => $previous?->value_balance ?? '0.00',
                ]);
            }

            $reversal = app(JournalReversalService::class)->reverse(
                $movement->journalEntry()->firstOrFail(),
                [
                    'posted_on' => $postedOn,
                    'fiscal_period_id' => $period->id,
                    'description' => 'Pembalikan persediaan '.$movement->reference.': '.$data['reason'],
                    'idempotency_key' => 'inventory-reversal-'.$movement->id,
                    'created_by' => $data['created_by'] ?? null,
                ],
            );
            $movement->update([
                'reversed_at' => now(),
                'reversal_journal_entry_id' => $reversal->id,
                'reversal_reason' => $data['reason'],
            ]);

            return $movement->fresh();
        });
    }

    /** @param  array{company_id:int, idempotency_key:string}  $data */
    private function existing(array $data): ?InventoryMovement
    {
        return InventoryMovement::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->first();
    }

    /** @param  array{company_id:int, branch_id:int, fiscal_period_id:int, moved_on:string}  $data */
    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open' || $data['moved_on'] < $period->starts_on->toDateString() || $data['moved_on'] > $period->ends_on->toDateString()) {
            throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal ini.');
        }
    }

    /** @param  array{company_id:int, branch_id:int, inventory_item_id:int, moved_on:string}  $data */
    private function guardDate(array $data): void
    {
        $later = InventoryMovement::where('company_id', $data['company_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('inventory_item_id', $data['inventory_item_id'])
            ->whereNull('reversed_at')
            ->whereDate('moved_on', '>', $data['moved_on'])
            ->exists();
        if ($later) {
            throw new InvalidArgumentException('Ada gerakan persediaan yang lebih baru pada jenis dan cabang ini. Balik gerakan itu terlebih dahulu.');
        }
    }

    private function lockLayer(array $data, InventoryItem $item, bool $create): ?InventoryCostLayer
    {
        $keys = ['company_id' => $data['company_id'], 'branch_id' => $data['branch_id'], 'inventory_item_id' => $item->id];
        if ($create) {
            InventoryCostLayer::firstOrCreate($keys);
        }

        return InventoryCostLayer::where($keys)->lockForUpdate()->first();
    }

    private function quantity(mixed $quantity): string
    {
        $quantity = bcadd((string) $quantity, '0', 4);
        if (bccomp($quantity, '0', 4) <= 0) {
            throw new InvalidArgumentException('Quantity persediaan harus lebih dari nol.');
        }

        return $quantity;
    }

    private function nextReferenceLocked(int $companyId, string $movedOn): string
    {
        $prefix = 'PRS-'.substr($movedOn, 0, 4).'-';
        $last = InventoryMovement::where('company_id', $companyId)->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->lockForUpdate()->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    /** @param  array<int, array{account_id:int, debit:string, credit:string}>  $lines */
    private function post(array $data, InventoryMovement $movement, array $lines, string $reference): JournalEntry
    {
        return app(JournalPostingService::class)->post([
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'fiscal_period_id' => $data['fiscal_period_id'],
            'posted_on' => $data['moved_on'],
            'description' => $data['description'] ?? 'Persediaan '.$reference,
            'idempotency_key' => $data['idempotency_key'],
            'source_type' => self::class,
            'source_kind' => 'inventory',
            'source_id' => (string) $movement->id,
            'reference' => $reference,
            'created_by' => $data['created_by'] ?? null,
            'lines' => $lines,
        ]);
    }
}
