<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['posted_on' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function fiscalPeriod(): BelongsTo { return $this->belongsTo(FiscalPeriod::class); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }

    public function reversal(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
    public function reversedEntry(): HasOne { return $this->hasOne(self::class, 'reversal_of_id'); }
}
