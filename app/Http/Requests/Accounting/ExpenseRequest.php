<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['company_id' => $this->user()?->company_id]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'fiscal_period_id' => ['required', 'integer'],
            'expense_account_id' => ['required', 'integer'],
            'payable_account_id' => ['required_without:payment_account_id', 'integer'],
            'payment_account_id' => ['required_without:payable_account_id', 'integer'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
