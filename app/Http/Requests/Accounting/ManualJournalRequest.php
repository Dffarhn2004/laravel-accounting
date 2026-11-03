<?php

namespace App\Http\Requests\Accounting;

use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\LedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))->map(function ($line) {
            $line['debit'] = ($line['debit'] ?? '') === '' ? '0' : $line['debit'];
            $line['credit'] = ($line['credit'] ?? '') === '' ? '0' : $line['credit'];

            return $line;
        })->all();
        $this->merge(['company_id' => $this->user()?->company_id, 'lines' => $lines]);
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'company_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'posted_on' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:2', 'max:6'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'lines.*.credit' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
        ] + AttachmentService::rules();
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Cabang wajib dipilih.',
            'posted_on.required' => 'Tanggal wajib diisi.',
            'description.required' => 'Keterangan wajib diisi.',
            'lines.min' => 'Jurnal manual berisi minimal 2 baris.',
            'lines.max' => 'Jurnal manual berisi paling banyak 6 baris.',
            'lines.*.account_id.required' => 'Setiap baris wajib punya akun.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $userBranch = $this->user()?->branch_id;
            if ($userBranch && (int) $this->input('branch_id') !== (int) $userBranch) {
                $validator->errors()->add('branch_id', 'Cabang ini bukan cabang Anda.');
            }

            $debit = '0.00';
            $credit = '0.00';
            $seen = [];
            foreach ($this->input('lines', []) as $index => $line) {
                $lineDebit = LedgerService::money($line['debit'] ?? '0');
                $lineCredit = LedgerService::money($line['credit'] ?? '0');
                $hasDebit = bccomp($lineDebit, '0', 2) > 0;
                $hasCredit = bccomp($lineCredit, '0', 2) > 0;
                if ($hasDebit === $hasCredit) {
                    $validator->errors()->add("lines.{$index}.debit", 'Setiap baris diisi debit atau kredit, tidak keduanya dan tidak kosong.');
                }
                $accountId = (int) ($line['account_id'] ?? 0);
                if ($accountId && isset($seen[$accountId])) {
                    $validator->errors()->add("lines.{$index}.account_id", 'Satu akun hanya boleh muncul sekali dalam jurnal manual.');
                }
                $seen[$accountId] = true;
                $debit = bcadd($debit, $lineDebit, 2);
                $credit = bcadd($credit, $lineCredit, 2);
            }
            if (bccomp($debit, $credit, 2) !== 0) {
                $validator->errors()->add('lines', 'Total debit harus sama dengan total kredit.');
            }
        });
    }
}
