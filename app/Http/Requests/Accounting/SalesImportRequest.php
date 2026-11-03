<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class SalesImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx'],
            'branch_id' => ['required', 'integer'],
            'channel' => ['required', 'string', 'max:80'],
            'receipt_account_id' => ['required', 'integer'],
            'revenue_account_id' => ['required', 'integer'],
            'other_income_account_id' => ['required', 'integer'],
            'admin_account_id' => ['required', 'integer'],
            'idempotency_key' => [$this->routeIs('inputan.impor.store') ? 'required' : 'nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Berkas paling besar 2 MB.',
            'file.extensions' => 'Berkas harus CSV atau XLSX.',
        ];
    }
}
