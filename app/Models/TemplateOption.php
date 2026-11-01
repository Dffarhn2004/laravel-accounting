<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateOption extends Model
{
    protected $table = 'journal_template_options';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
