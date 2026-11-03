<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Input Pembelian Asset (PRD 03 #6). Payment and asset class come from Template Jurnal. */
class AssetAcquisitionRequest extends FormRequest
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
            'acquired_on' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'string', Rule::exists('journal_template_options', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('group', 'outflow_payment')->where('is_active', true))],
            'asset_account_id' => ['required', 'integer', Rule::exists('asset_categories', 'asset_account_id')->where('company_id', $companyId)],
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999999'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ] + AttachmentService::rules();
    }

    public function messages(): array
    {
        return [
            'payment_method.exists' => 'Jenis pembayaran tidak ada di Template Jurnal bagian 7.',
            'asset_account_id.exists' => 'Akun aset belum punya kelompok di Template Jurnal bagian 5.',
        ];
    }
}
