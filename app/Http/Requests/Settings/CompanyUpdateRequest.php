<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($companyId)],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_bank_name' => ['nullable', 'string', 'max:255'],
            'invoice_bank_account_name' => ['nullable', 'string', 'max:255'],
            'invoice_bank_account_number' => ['nullable', 'string', 'max:100'],
            'default_vat_rate' => ['required', 'decimal:0,2', 'between:0,100'],
            'default_pph23_rate' => ['required', 'decimal:0,2', 'between:0,100'],
        ];
    }
}
