<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $guarded = [];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function lines(): HasMany { return $this->hasMany(BudgetMonthlyLine::class); }
    public function monthlyLines(): HasMany { return $this->lines(); }
}
