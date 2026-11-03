<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FixedAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'depreciation_from' => 'date',
            'cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'useful_life_months' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function postings(): HasMany
    {
        return $this->hasMany(DepreciationPosting::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(DepreciationSchedule::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'settlement_account_id');
    }

    public function accumulatedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    public function reversalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
