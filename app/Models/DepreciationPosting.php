<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationPosting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function asset(): BelongsTo { return $this->belongsTo(FixedAsset::class, 'fixed_asset_id'); }
    public function schedule(): BelongsTo { return $this->belongsTo(DepreciationSchedule::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
