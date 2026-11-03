<?php

namespace App\Http\Requests\Accounting;

use App\Models\Invoice;
use App\Services\Accounting\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivableSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'received_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'pph23_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ] + AttachmentService::rules();
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Pilih invoice yang dilunasi.',
            'received_on.required' => 'Tanggal bayar wajib diisi.',
            'payment_method.required' => 'Jenis pembayaran wajib dipilih.',
            'amount.required' => 'Jumlah dibayar wajib diisi.',
            'amount.gt' => 'Jumlah dibayar harus lebih dari nol.',
            'description.required' => 'Keterangan wajib diisi.',
            'attachments.max' => 'Bukti paling banyak 5 file.',
            'attachments.*.max' => 'Setiap bukti paling besar 5 MB.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $invoice = Invoice::where('company_id', $this->user()?->company_id)->find($this->input('invoice_id'));
            $userBranch = $this->user()?->branch_id;
            if ($invoice && $userBranch && (int) $invoice->branch_id !== (int) $userBranch) {
                $validator->errors()->add('invoice_id', 'Invoice ini milik cabang lain.');
            }
        });
    }
}
