<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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
        $companyId = $this->user()?->company_id;

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'fiscal_period_id' => ['required', 'integer', Rule::exists('fiscal_periods', 'id')->where('company_id', $companyId)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'invoice_number' => ['nullable', 'string', 'max:100', Rule::unique('invoices', 'invoice_number')->where('company_id', $companyId)],
            'issued_on' => ['required', 'date'],
            'receivable_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'revenue_account_id' => ['nullable', 'required_without:lines.0.revenue_account_id', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'discount_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'paid_amount' => ['nullable', 'decimal:0,2', 'gte:0'],
            'payment_account_id' => ['nullable', Rule::requiredIf(fn () => bccomp((string) $this->input('paid_amount', '0'), '0', 2) > 0), 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'other_incomes' => ['nullable', 'array', 'max:3'],
            'other_incomes.*.label' => ['required', 'string', 'max:255'],
            'other_incomes.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'other_incomes.*.amount' => ['required', 'decimal:0,2', 'gt:0'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.revenue_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'idempotency_key' => ['required', 'string', 'max:150'],
            'vat_enabled' => ['required', 'boolean'],
            'vat_rate' => ['required_if:vat_enabled,1', 'nullable', 'decimal:0,2', 'between:0,100'],
            'vat_output_account_id' => ['required_if:vat_enabled,1', 'nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'pph23_rate' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'lines' => ['required', 'array', 'min:1', 'max:15'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.discount_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
        ] + AttachmentService::rules();
    }
}
