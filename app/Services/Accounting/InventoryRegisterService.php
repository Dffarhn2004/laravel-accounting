<?php

namespace App\Services\Accounting;

use App\Models\InventoryMovement;
use App\Models\TemplateOption;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data Persediaan (PRD 04 #3): one row per movement, 19 columns.
 */
class InventoryRegisterService
{
    public const PER_PAGE = 25;

    public const COLUMNS = [
        'Aksi', 'Bukti', 'No', 'Bulan', 'Tanggal', 'Cabang', 'Jenis Transaksi', 'Jenis Persediaan',
        'Kode Akun', 'Nama Akun', 'Keterangan', 'Qty', 'Harga Satuan', 'Ongkir', 'Pajak', 'Total Harga',
        'Kode Lawan', 'Nama Akun Lawan', 'Nilai Lawan',
    ];

    /** @param  array{search?:?string, branch_id?:?int}  $filters */
    public function query(int $companyId, array $filters, ?int $restrictToBranch = null): Builder
    {
        return InventoryMovement::query()
            ->where('company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('item', fn ($a) => $a->where('sku', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('settlementAccount', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('moved_on')->orderByDesc('id');
    }

    /** @return array{amount:string, count:int} */
    public function totals(Builder $query): array
    {
        $live = (clone $query)->reorder()->whereNull('reversed_at');

        return ['amount' => bcadd((string) (clone $live)->sum('total_cost'), '0', 2), 'count' => (clone $live)->count()];
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $movements, int $startNo = 1): array
    {
        $movements->loadMissing([
            'branch:id,name', 'item:id,sku,name,inventory_account_id,cogs_account_id',
            'item.inventoryAccount:id,code,name', 'item.cogsAccount:id,code,name',
            'settlementAccount:id,code,name', 'attachments:id,attachable_id,attachable_type,original_name,mime',
        ]);
        $methods = TemplateOption::where('group', 'outflow_payment')->whereIn('company_id', $movements->pluck('company_id')->unique())->pluck('label', 'code');
        $latest = $this->latestIds($movements);

        $out = [];
        foreach ($movements->values() as $i => $movement) {
            $issue = $movement->movement_type === 'issue';
            $counter = $issue ? $movement->item?->cogsAccount : $movement->settlementAccount;
            $out[] = [
                'movement_id' => $movement->id,
                'reversed' => $movement->reversed_at !== null,
                'can_reverse' => $movement->reversed_at === null && ($latest[$movement->branch_id.'-'.$movement->inventory_item_id] ?? null) === $movement->id,
                'reference' => $movement->reference,
                'attachments' => $movement->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name, 'mime' => $a->mime])->values()->all(),
                'no' => $startNo + $i,
                'month' => LedgerService::MONTHS[(int) $movement->moved_on->format('n')].' '.$movement->moved_on->format('Y'),
                'date' => $movement->moved_on->toDateString(),
                'branch' => $movement->branch?->name,
                'kind' => $issue ? 'Pemakaian' : ($methods[$movement->payment_method] ?? $movement->payment_method),
                'item' => $movement->item ? $movement->item->sku.' — '.$movement->item->name : null,
                'account_code' => $movement->item?->inventoryAccount?->code,
                'account_name' => $movement->item?->inventoryAccount?->name,
                'description' => $movement->description,
                'quantity' => (string) $movement->quantity,
                'unit_cost' => (string) $movement->unit_cost,
                'freight' => (string) $movement->freight,
                'tax' => (string) $movement->tax,
                'total' => (string) $movement->total_cost,
                'counter_code' => $counter?->code,
                'counter_name' => $counter?->name,
                'counter_amount' => (string) $movement->total_cost,
            ];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $row */
    public static function csvRow(array $row): array
    {
        return [
            '', count($row['attachments']) ?: '', $row['no'], $row['month'], $row['date'], Csv::cell($row['branch']), Csv::cell($row['kind']),
            Csv::cell($row['item']), Csv::cell($row['account_code']), Csv::cell($row['account_name']), Csv::cell($row['description']),
            $row['quantity'], $row['unit_cost'], $row['freight'], $row['tax'], $row['total'],
            Csv::cell($row['counter_code']), Csv::cell($row['counter_name']), $row['counter_amount'],
            $row['reversed'] ? 'Dibalik' : 'Aktif',
        ];
    }

    /** @param  Collection<int, InventoryMovement>  $movements  @return array<string, int> */
    private function latestIds(Collection $movements): array
    {
        $pairs = $movements->map(fn ($m) => [$m->branch_id, $m->inventory_item_id])->unique();
        $out = [];
        foreach ($pairs as [$branchId, $itemId]) {
            $id = InventoryMovement::where('branch_id', $branchId)->where('inventory_item_id', $itemId)->whereNull('reversed_at')
                ->orderByDesc('moved_on')->orderByDesc('id')->value('id');
            if ($id) {
                $out[$branchId.'-'.$itemId] = (int) $id;
            }
        }

        return $out;
    }
}
