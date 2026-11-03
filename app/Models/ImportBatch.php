<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ImportOrder::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
