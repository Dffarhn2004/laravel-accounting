<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['minimum_stock' => 'decimal:4'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function inventoryAccount(): BelongsTo { return $this->belongsTo(Account::class, 'inventory_account_id'); }
    public function cogsAccount(): BelongsTo { return $this->belongsTo(Account::class, 'cogs_account_id'); }
    public function revenueAccount(): BelongsTo { return $this->belongsTo(Account::class, 'revenue_account_id'); }
    public function movements(): HasMany { return $this->hasMany(InventoryMovement::class); }
}
