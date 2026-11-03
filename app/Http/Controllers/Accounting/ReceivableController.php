<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ReceivableSettlementRequest;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\TemplateOption;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ReceivableController extends Controller
{
    public function create(Request $request): Response
    {
        $companyId = $request->user()?->company_id;
        $branchId = $request->user()?->branch_id;

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['unpaid', 'partial'])
            ->with(['customer:id,name', 'branch:id,name', 'receivableAccount:id,code,name'])
            ->orderBy('issued_on')
            ->orderBy('id')
            ->get();
        $ids = $invoices->pluck('id');
        $paid = ReceiptAllocation::query()
            ->whereIn('invoice_id', $ids)
            ->whereHas('receipt', fn ($q) => $q->whereNull('reversed_at'))
            ->selectRaw('invoice_id, SUM(amount) as paid')
            ->groupBy('invoice_id')
            ->pluck('paid', 'invoice_id');
        $withheld = Receipt::query()
            ->whereIn('invoice_id', $ids)
            ->whereNull('reversed_at')
            ->selectRaw('invoice_id, SUM(pph23_amount) as withheld')
            ->groupBy('invoice_id')
            ->pluck('withheld', 'invoice_id');

        $rows = $invoices->map(function (Invoice $invoice) use ($paid, $withheld) {
            $settled = LedgerService::money($paid[$invoice->id] ?? '0');
            $remaining = bcsub((string) $invoice->total, $settled, 2);
            $pphLeft = bcsub((string) $invoice->pph23_amount, LedgerService::money($withheld[$invoice->id] ?? '0'), 2);

            return [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'issued_on' => $invoice->issued_on->toDateString(),
                'customer_id' => $invoice->customer_id,
                'customer' => $invoice->customer?->name,
                'branch' => $invoice->branch?->name,
                'receivable_code' => $invoice->receivableAccount?->code,
                'receivable_name' => $invoice->receivableAccount?->name,
                'total' => LedgerService::money($invoice->total),
                'remaining' => $remaining,
                'pph23_left' => bccomp($pphLeft, '0', 2) > 0 ? $pphLeft : '0.00',
            ];
        })->filter(fn ($row) => bccomp($row['remaining'], '0', 2) > 0)->values();

        $methods = TemplateOption::query()
            ->with('account')
            ->where('company_id', $companyId)
            ->where('group', 'invoice_payment')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (TemplateOption $option) => $option->code !== 'piutang' && $option->account && $option->account->is_active && $option->account->type === 'asset')
            ->map(fn (TemplateOption $option) => [
                'code' => $option->code,
                'label' => $option->label,
                'account' => $option->account->code.' '.$option->account->name,
            ])->values();

        return Inertia::render('accounting/receivable-form', [
            'invoices' => $rows,
            'methods' => $methods,
            'summary' => [
                'customers' => $rows->pluck('customer_id')->unique()->count(),
                'invoices' => $rows->count(),
                'outstanding' => LedgerService::money($rows->reduce(fn ($c, $row) => bcadd($c, $row['remaining'], 2), '0.00')),
            ],
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function store(ReceivableSettlementRequest $request, TransactionPostingService $posting, AttachmentService $attachments): RedirectResponse
    {
        abort_unless($request->user()->company_id, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);

        try {
            $receipt = DB::transaction(function () use ($request, $posting, $attachments, $data, $files) {
                $receipt = $posting->collectReceivable([
                    'company_id' => $request->user()->company_id,
                    'invoice_id' => (int) $data['invoice_id'],
                    'received_on' => $data['received_on'],
                    'payment_method' => $data['payment_method'],
                    'amount' => (string) $data['amount'],
                    'pph23_amount' => (string) ($data['pph23_amount'] ?? '0'),
                    'description' => $data['description'],
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $request->user()->id,
                ]);
                if ($files !== []) {
                    $attachments->attach($receipt, $request->user()->company_id, $files, $request->user()->id);
                }

                return $receipt;
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('data.piutang.index')->with('success', "Pelunasan {$receipt->receipt_number} berhasil disimpan.");
    }
}
