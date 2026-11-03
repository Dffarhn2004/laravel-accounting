<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input Persediaan (PRD 03 #4). Payment accounts come from Template Jurnal bagian 7.
 * Pemakaian has no cash account; its unit cost is the branch moving average.
 */
class InventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $issue = $this->input('kind') === 'issue';

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'moved_on' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::in(['purchase', 'issue'])],
            'payment_method' => [$issue ? 'nullable' : 'required', 'string', Rule::exists('journal_template_options', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('group', 'outflow_payment')->where('is_active', true))],
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:999999999999'],
            'unit_cost' => [$issue ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'gte:0', 'max:999999999999999'],
            'freight' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0', 'max:999999999999999'],
            'tax' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0', 'max:999999999999999'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ] + AttachmentService::rules();
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.exists' => 'Pilih jenis persediaan yang aktif. Atur di Template Jurnal bagian 4.',
            'payment_method.exists' => 'Jenis pembayaran tidak tersedia. Atur di Template Jurnal bagian 7.',
            'payment_method.required' => 'Jenis transaksi wajib dipilih.',
        ];
    }
}
