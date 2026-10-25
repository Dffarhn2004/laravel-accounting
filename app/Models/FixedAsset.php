<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FixedAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function fiscalPeriod(): BelongsTo { return $this->belongsTo(FiscalPeriod::class); }
    public function postings(): HasMany { return $this->hasMany(DepreciationPosting::class); }
    public function schedule(): HasOne { return $this->hasOne(DepreciationSchedule::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
