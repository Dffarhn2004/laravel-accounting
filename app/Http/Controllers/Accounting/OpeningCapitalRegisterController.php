<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\OpeningBalance;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\OpeningCapitalRegisterService;
use App\Services\Accounting\OpeningPositionService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpeningCapitalRegisterController extends Controller
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

    private function authorizeOpening(Request $request, OpeningBalance $opening): void
    {
        abort_unless($opening->company_id === $request->user()->company_id && $opening->method === OpeningPositionService::MODAL_AWAL, 404);
        abort_if($request->user()->branch_id && $opening->branch_id !== $request->user()->branch_id, 404);
    }

    public function index(Request $request, OpeningCapitalRegisterService $register): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $query = $register->query($companyId, $filters, $request->user()->branch_id);
        $page = $query->clone()->paginate(OpeningCapitalRegisterService::PER_PAGE)->withQueryString();

        return Inertia::render('accounting/data-modal-awal', [
            'rows' => $register->rows($page->getCollection()),
            'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'totals' => $register->totals($query),
            'filters' => $filters,
            'columns' => OpeningCapitalRegisterService::COLUMNS,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function export(Request $request, OpeningCapitalRegisterService $register): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $query = $register->query($companyId, $this->filters($request, $companyId), $request->user()->branch_id);

        return response()->streamDownload(function () use ($query, $register) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, [...OpeningCapitalRegisterService::COLUMNS, 'Status']);
            $no = 1;
            $query->clone()->chunkById(100, function ($openings) use ($out, $register, &$no) {
                foreach ($register->rows($openings, $no) as $row) {
                    fputcsv($out, OpeningCapitalRegisterService::csvRow($row));
                    $no = $row['no'] + 1;
                }
            });
            fclose($out);
        }, 'data-modal-awal-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, OpeningBalance $opening, OpeningPositionService $openings): RedirectResponse
    {
        $this->authorizeOpening($request, $opening);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'posted_on' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'reason.required' => 'Alasan pembalikan wajib diisi.',
            'reason.min' => 'Alasan pembalikan minimal 3 karakter.',
        ]);

        try {
            $openings->reverseModal($opening, [
                'company_id' => $opening->company_id,
                'reason' => $data['reason'],
                'posted_on' => $data['posted_on'] ?? now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['modal' => $e->getMessage()]);
        }

        return back()->with('success', 'Setoran modal awal dibalik. Cabang ini boleh menyetor ulang.');
    }

    public function attach(Request $request, OpeningBalance $opening, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeOpening($request, $opening);
        $request->validate(AttachmentService::rules() + ['attachments' => ['required', 'array', 'min:1', 'max:'.AttachmentService::MAX_FILES]]);
        DB::transaction(fn () => $attachments->attach($opening, $opening->company_id, $request->file('attachments', []), $request->user()->id));

        return back()->with('success', 'Bukti ditambahkan.');
    }
}
