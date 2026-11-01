<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function fiscalPeriod(): BelongsTo { return $this->belongsTo(FiscalPeriod::class); }
    public function payments(): HasMany { return $this->hasMany(ExpensePayment::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function expenseAccount(): BelongsTo { return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function settlementAccount(): BelongsTo { return $this->belongsTo(Account::class, 'settlement_account_id'); }
}
