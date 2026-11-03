<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Receipt;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\ReceivableRegisterService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data Pelunasan Piutang. Corrections are reversals with a reason, never edits or deletes.
 */
class ReceivableRegisterController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

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

    private function authorizeReceipt(Request $request, Receipt $receipt): void
    {
        $user = $request->user();
        abort_unless($receipt->company_id === $user->company_id, 404);
        abort_if($user->branch_id && $receipt->branch_id !== $user->branch_id, 404);
    }

    public function index(Request $request, ReceivableRegisterService $register): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $query = $register->query($companyId, $filters, $request->user()->branch_id);
        $page = $query->clone()->paginate(ReceivableRegisterService::PER_PAGE)->withQueryString();
        $startNo = ($page->currentPage() - 1) * ReceivableRegisterService::PER_PAGE + 1;

        return Inertia::render('accounting/data-piutang', [
            'rows' => $register->rows($page->getCollection(), $startNo),
            'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'totals' => $register->totals($query),
            'filters' => $filters,
            'columns' => ReceivableRegisterService::COLUMNS,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function export(Request $request, ReceivableRegisterService $register): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $query = $register->query($companyId, $this->filters($request, $companyId), $request->user()->branch_id);

        return response()->streamDownload(function () use ($query, $register) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, [...ReceivableRegisterService::COLUMNS, 'Status']);
            $no = 1;
            $query->clone()->chunkById(200, function ($receipts) use ($out, $register, &$no) {
                foreach ($register->rows($receipts, $no) as $row) {
                    fputcsv($out, ReceivableRegisterService::csvRow($row));
                    $no = $row['no'] + 1;
                }
            }, 'id', 'id');
            fclose($out);
        }, 'data-pelunasan-piutang-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, Receipt $receipt, TransactionPostingService $posting): RedirectResponse
    {
        $this->authorizeReceipt($request, $receipt);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'posted_on' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'reason.required' => 'Alasan pembalikan wajib diisi.',
            'reason.min' => 'Alasan pembalikan minimal 3 karakter.',
        ]);

        try {
            $posting->reverseReceipt($receipt, [
                'company_id' => $receipt->company_id,
                'reason' => $data['reason'],
                'posted_on' => $data['posted_on'] ?? now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['receivable' => $e->getMessage()]);
        }

        return back()->with('success', 'Pelunasan '.$receipt->receipt_number.' dibalik. Invoice kembali sesuai sisa yang masih hidup.');
    }

    public function attach(Request $request, Receipt $receipt, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeReceipt($request, $receipt);
        $request->validate(AttachmentService::rules() + ['attachments' => ['required', 'array', 'min:1', 'max:'.AttachmentService::MAX_FILES]]);

        DB::transaction(fn () => $attachments->attach($receipt, $receipt->company_id, $request->file('attachments', []), $request->user()->id));

        return back()->with('success', 'Bukti ditambahkan.');
    }
}
