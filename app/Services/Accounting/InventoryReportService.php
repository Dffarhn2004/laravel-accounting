<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Persediaan (PRD 05 #7). Opening, in, out and ending come from live movements
 * up to the end of the selected month, not from today's cost layer.
 */
class InventoryReportService
{
    /**
     * @param  array{company_id:int, branch_id?:?int, year:int, month:int, item_id?:?int}  $filters
     * @return array{rows:array<int, array<string, mixed>>, card:array<int, array<string, mixed>>, reconciliation:array{documents:string, ledger:string, difference:string}, as_of:string}
     */
    public function build(array $filters): array
    {
        $companyId = (int) $filters['company_id'];
        $branchId = $filters['branch_id'] ?? null;
        $itemId = $filters['item_id'] ?? null;
        $from = sprintf('%04d-%02d-01', $filters['year'], $filters['month']);
        $to = Carbon::parse($from)->endOfMonth()->toDateString();

        $items = InventoryItem::where('company_id', $companyId)->when($itemId, fn ($q, $id) => $q->whereKey($id))->orderBy('sku')->get();
        $branches = Branch::where('company_id', $companyId)->when($branchId, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get();
        $movements = InventoryMovement::query()
            ->where('company_id', $companyId)
            ->whereDate('moved_on', '<=', $to)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($itemId, fn ($q, $id) => $q->where('inventory_item_id', $id))
            ->with('reversalJournal:id,posted_on')
            ->orderBy('moved_on')->orderBy('id')
            ->get()
            ->filter(fn (InventoryMovement $movement) => $this->liveAt($movement, $to))
            ->groupBy(fn ($m) => $m->branch_id.'-'.$m->inventory_item_id);

        $rows = [];
        $documents = '0.00';
        foreach ($branches as $branch) {
            foreach ($items as $item) {
                $list = $movements->get($branch->id.'-'.$item->id, collect());
                $openingQty = '0.0000';
                $openingValue = '0.00';
                $inQty = '0.0000';
                $inValue = '0.00';
                $outQty = '0.0000';
                $outValue = '0.00';
                $endQty = '0.0000';
                $endValue = '0.00';
                foreach ($list as $movement) {
                    $date = $movement->moved_on->toDateString();
                    if ($date < $from) {
                        $openingQty = (string) $movement->quantity_balance;
                        $openingValue = (string) $movement->value_balance;
                        $endQty = $openingQty;
                        $endValue = $openingValue;

                        continue;
                    }
                    if ($movement->movement_type === 'purchase') {
                        $inQty = bcadd($inQty, (string) $movement->quantity, 4);
                        $inValue = bcadd($inValue, (string) $movement->total_cost, 2);
                    } else {
                        $outQty = bcadd($outQty, (string) $movement->quantity, 4);
                        $outValue = bcadd($outValue, (string) $movement->total_cost, 2);
                    }
                    $endQty = (string) $movement->quantity_balance;
                    $endValue = (string) $movement->value_balance;
                }
                $average = bccomp($endQty, '0', 4) > 0 ? bcdiv($endValue, $endQty, 2) : '0.00';
                $low = bccomp($endQty, (string) $item->minimum_stock, 4) < 0;
                $documents = bcadd($documents, $endValue, 2);
                $rows[] = [
                    'branch' => $branch->name,
                    'item' => $item->sku.' — '.$item->name,
                    'item_id' => $item->id,
                    'opening_qty' => $openingQty,
                    'opening_value' => $openingValue,
                    'in_qty' => $inQty,
                    'in_value' => $inValue,
                    'out_qty' => $outQty,
                    'out_value' => $outValue,
                    'end_qty' => $endQty,
                    'end_value' => $endValue,
                    'average' => $average,
                    'minimum' => (string) $item->minimum_stock,
                    'status' => $low ? 'Di bawah minimum' : 'Aman',
                ];
            }
        }

        return [
            'rows' => $rows,
            'card' => $itemId ? $this->card($movements, $branches, $items->first(), $from) : [],
            'reconciliation' => $this->reconcile($companyId, $branchId, $to, $documents),
            'as_of' => $to,
        ];
    }

    /** @param  Collection<string, Collection<int, InventoryMovement>>  $grouped */
    private function card($grouped, $branches, ?InventoryItem $item, string $from): array
    {
        if (! $item) {
            return [];
        }
        $card = [];
        foreach ($branches as $branch) {
            $list = $grouped->get($branch->id.'-'.$item->id, collect());
            $qty = '0.0000';
            $value = '0.00';
            $started = false;
            foreach ($list as $movement) {
                $date = $movement->moved_on->toDateString();
                if ($date < $from) {
                    $qty = (string) $movement->quantity_balance;
                    $value = (string) $movement->value_balance;

                    continue;
                }
                if (! $started) {
                    $card[] = $this->cardRow($branch->name, $from, 'Saldo awal', 'Saldo', '0.0000', '0.0000', $qty, '0.00', '0.00', $value);
                    $started = true;
                }
                $inQty = $movement->movement_type === 'purchase' ? (string) $movement->quantity : '0.0000';
                $outQty = $movement->movement_type === 'issue' ? (string) $movement->quantity : '0.0000';
                $inValue = $movement->movement_type === 'purchase' ? (string) $movement->total_cost : '0.00';
                $outValue = $movement->movement_type === 'issue' ? (string) $movement->total_cost : '0.00';
                $qty = (string) $movement->quantity_balance;
                $value = (string) $movement->value_balance;
                $card[] = $this->cardRow($branch->name, $date, $movement->description ?: $movement->reference, $movement->movement_type === 'purchase' ? 'Pembelian' : 'Pemakaian', $inQty, $outQty, $qty, $inValue, $outValue, $value);
            }
            if (! $started && bccomp($qty, '0', 4) > 0) {
                $card[] = $this->cardRow($branch->name, $from, 'Saldo awal', 'Saldo', '0.0000', '0.0000', $qty, '0.00', '0.00', $value);
            }
        }

        return $card;
    }

    /** A reversal dated after the report still leaves the movement inside that month. */
    private function liveAt(InventoryMovement $movement, string $to): bool
    {
        if ($movement->reversed_at === null) {
            return true;
        }
        $reversedOn = $movement->reversalJournal?->posted_on?->toDateString();

        return $reversedOn !== null && $reversedOn > $to;
    }

    /** @return array<string, mixed> */
    private function cardRow(string $branch, string $date, ?string $description, string $kind, string $inQty, string $outQty, string $qty, string $inValue, string $outValue, string $value): array
    {
        return [
            'branch' => $branch, 'date' => $date, 'description' => $description, 'kind' => $kind,
            'in_qty' => $inQty, 'out_qty' => $outQty, 'qty' => $qty, 'in_value' => $inValue, 'out_value' => $outValue, 'value' => $value,
        ];
    }

    /** @return array{documents:string, ledger:string, difference:string} */
    private function reconcile(int $companyId, ?int $branchId, string $to, string $documents): array
    {
        $branches = $branchId ? [$branchId] : Branch::where('company_id', $companyId)->pluck('id')->all();
        $accounts = Account::where('company_id', $companyId)->where('type', 'asset')->where('module', 'Persediaan')->get(['id', 'type', 'is_contra']);
        $ledger = '0.00';
        foreach ($branches as $branch) {
            $nets = app(BalanceSheetService::class)->nets($companyId, (int) $branch, $to);
            foreach ($accounts as $account) {
                $ledger = bcadd($ledger, LedgerService::normalSigned($nets[$account->id] ?? '0.00', $account->type, (bool) $account->is_contra), 2);
            }
        }

        return ['documents' => $documents, 'ledger' => $ledger, 'difference' => bcsub($ledger, $documents, 2)];
    }
}
