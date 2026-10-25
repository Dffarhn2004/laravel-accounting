<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['posted_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function fiscalPeriod(): BelongsTo { return $this->belongsTo(FiscalPeriod::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
