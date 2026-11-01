<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\RevenueRegisterService;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data Pendapatan: the invoice revenue register (PRD 04-DATA-TRANSAKSI.md #1).
 * Corrections are reversals with a reason, never edits or deletes of posted journals.
 */
class RevenueRegisterController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{search:?string, branch_id:?int, from:?string, to:?string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return $data + ['search' => null, 'branch_id' => null, 'from' => null, 'to' => null];
    }

    /** A user bound to one branch only ever sees and touches that branch. */
    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        abort_unless($invoice->company_id === $user->company_id, 404);
        abort_if($user->branch_id && $invoice->branch_id !== $user->branch_id, 404);
    }

    public function index(Request $request, RevenueRegisterService $register): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $query = $register->query($companyId, $filters, $request->user()->branch_id);

        $page = $query->clone()->paginate(RevenueRegisterService::PER_PAGE)->withQueryString();
        $startNo = $register->rowCountBefore($query, ($page->currentPage() - 1) * RevenueRegisterService::PER_PAGE) + 1;

        return Inertia::render('accounting/data-pendapatan', [
            'rows' => $register->rows($page->getCollection(), $startNo),
            'pagination' => [
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'total' => $page->total(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'totals' => $register->totals($query),
            'filters' => $filters,
            'columns' => RevenueRegisterService::COLUMNS,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    /** CSV of the same filtered register (not just the visible page). */
    public function export(Request $request, RevenueRegisterService $register): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $query = $register->query($companyId, $this->filters($request, $companyId), $request->user()->branch_id);

        return response()->streamDownload(function () use ($query, $register) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Indonesian text correctly
            fputcsv($out, [...RevenueRegisterService::COLUMNS, 'Status']);
            $no = 1;
            $query->clone()->chunkById(200, function ($invoices) use ($out, $register, &$no) {
                foreach ($register->rows($invoices, $no) as $row) {
                    fputcsv($out, RevenueRegisterService::csvRow($row));
                    $no = $row['no'] + 1;
                }
            }, 'id', 'id');
            fclose($out);
        }, 'data-pendapatan-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, Invoice $invoice, TransactionPostingService $service): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'posted_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $service->reverseInvoice($invoice, $data + ['company_id' => $invoice->company_id, 'created_by' => $request->user()->id]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Invoice '.$invoice->invoice_number.' dibalik. Buat invoice baru untuk koreksi.');
    }

    public function attach(Request $request, Invoice $invoice, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        $request->validate(AttachmentService::rules() + ['attachments' => ['required', 'array', 'min:1', 'max:'.AttachmentService::MAX_FILES]]);

        DB::transaction(fn () => $attachments->attach($invoice, $invoice->company_id, $request->file('attachments', []), $request->user()->id));

        return back()->with('success', 'Bukti ditambahkan.');
    }
}
