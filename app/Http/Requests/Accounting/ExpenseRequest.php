<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input Beban (PRD 03 #3). The payment account is NOT taken from the browser: the server maps the
 * chosen payment method through Template Jurnal bagian 7, so that table governs the dropdown.
 */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'string', Rule::exists('journal_template_options', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('group', 'outflow_payment')->where('is_active', true))],
            'expense_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'expense')->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999999'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ] + AttachmentService::rules();
    }

    public function messages(): array
    {
        return [
            'expense_account_id.exists' => 'Pilih akun beban (awalan 5) yang aktif.',
            'payment_method.exists' => 'Jenis pembayaran tidak tersedia. Atur di Template Jurnal bagian 7.',
        ];
    }
}
