<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InventoryMovement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'moved_on' => 'date',
            'reversed_at' => 'datetime',
            'quantity' => 'decimal:4',
            'quantity_balance' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'freight' => 'decimal:2',
            'tax' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'value_balance' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'settlement_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
