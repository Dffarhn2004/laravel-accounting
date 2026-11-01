<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'reversed_at' => 'datetime', 'total' => 'decimal:2', 'dpp' => 'decimal:2', 'discount_total' => 'decimal:2', 'vat_rate' => 'decimal:2', 'vat_amount' => 'decimal:2', 'pph23_rate' => 'decimal:2', 'pph23_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'other_income_total' => 'decimal:2'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(Account::class, 'payment_account_id'); }
    public function vatOutputAccount(): BelongsTo { return $this->belongsTo(Account::class, 'vat_output_account_id'); }
    public function receivableAccount(): BelongsTo { return $this->belongsTo(Account::class, 'receivable_account_id'); }
    public function discountAccount(): BelongsTo { return $this->belongsTo(Account::class, 'discount_account_id'); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class); }
    public function otherIncomes(): HasMany { return $this->hasMany(InvoiceOtherIncome::class); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
    public function receipts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Receipt::class,
            ReceiptAllocation::class,
            'invoice_id',
            'id',
            'id',
            'receipt_id',
        );
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }
}
