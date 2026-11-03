<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'admin_amount' => 'decimal:2',
            'invoice_total' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function adminJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'admin_journal_entry_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ImportOrderMovement::class);
    }
}
