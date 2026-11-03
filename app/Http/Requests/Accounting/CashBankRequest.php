<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input Pindah Saldo Kas/Bank (PRD 03 #7). Dari/Ke accounts are checked again in
 * TreasuryPostingService against Template Jurnal sections 8-9; this request only
 * keeps them inside the company.
 */
class CashBankRequest extends FormRequest
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
            'posted_on' => ['required', 'date_format:Y-m-d'],
            'transaction_type' => ['required', 'string', Rule::exists('journal_template_options', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('group', 'treasury_type')->where('is_active', true))],
            'from_account_id' => ['required', 'integer', 'different:to_account_id', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'to_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'from_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999999'],
            'to_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999999'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ] + AttachmentService::rules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('from_amount');
            $to = $this->input('to_amount');
            if (! is_numeric($from) || ! is_numeric($to)) {
                return;
            }
            if (bccomp(bcadd((string) $from, '0', 2), bcadd((string) $to, '0', 2), 2) !== 0) {
                $validator->errors()->add('to_amount', 'Nilai Dari dan Nilai Ke harus sama.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'transaction_type.exists' => 'Jenis transaksi tidak tersedia. Atur di Template Jurnal bagian 8.',
            'from_account_id.different' => 'Akun Dari dan akun Ke harus berbeda.',
            'from_account_id.exists' => 'Akun Dari tidak aktif atau bukan milik usaha ini.',
            'to_account_id.exists' => 'Akun Ke tidak aktif atau bukan milik usaha ini.',
        ];
    }
}
