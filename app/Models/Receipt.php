<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function allocations(): HasMany { return $this->hasMany(ReceiptAllocation::class); }
}
