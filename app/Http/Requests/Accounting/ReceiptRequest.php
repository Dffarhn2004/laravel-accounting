<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'receivable_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'received_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['company_id' => $this->user()->company_id]);
    }
}
