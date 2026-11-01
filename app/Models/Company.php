<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name', 'code', 'address', 'phone', 'email', 'invoice_bank_name',
        'invoice_bank_account_name', 'invoice_bank_account_number',
        'default_vat_rate', 'default_pph23_rate',
    ];

    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function accounts(): HasMany { return $this->hasMany(Account::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function vendors(): HasMany { return $this->hasMany(Vendor::class); }
    public function fiscalPeriods(): HasMany { return $this->hasMany(FiscalPeriod::class); }
    public function journalEntries(): HasMany { return $this->hasMany(JournalEntry::class); }
    public function cashAccounts(): HasMany { return $this->hasMany(CashAccount::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function assetCategories(): HasMany { return $this->hasMany(AssetCategory::class); }
    public function templateOptions(): HasMany { return $this->hasMany(TemplateOption::class); }
    public function inventoryItems(): HasMany { return $this->hasMany(InventoryItem::class); }
}
