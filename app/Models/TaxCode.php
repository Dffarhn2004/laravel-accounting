<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
