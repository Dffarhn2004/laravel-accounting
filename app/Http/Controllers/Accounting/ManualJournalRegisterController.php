<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\ManualJournalRegisterService;
use App\Services\Accounting\ManualJournalService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualJournalRegisterController extends Controller
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

    private function authorizeEntry(Request $request, JournalEntry $entry): void
    {
        abort_unless($entry->company_id === $request->user()->company_id && $entry->source_kind === 'manual', 404);
        abort_if($request->user()->branch_id && $entry->branch_id !== $request->user()->branch_id, 404);
    }

    public function index(Request $request, ManualJournalRegisterService $register): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $query = $register->query($companyId, $filters, $request->user()->branch_id);
        $page = $query->clone()->paginate(ManualJournalRegisterService::PER_PAGE)->withQueryString();

        return Inertia::render('accounting/data-jurnal-manual', [
            'rows' => $register->rows($page->getCollection(), ($page->currentPage() - 1) * ManualJournalRegisterService::PER_PAGE + 1),
            'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'totals' => $register->totals($query),
            'filters' => $filters,
            'columns' => ManualJournalRegisterService::COLUMNS,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function export(Request $request, ManualJournalRegisterService $register): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $query = $register->query($companyId, $this->filters($request, $companyId), $request->user()->branch_id);

        return response()->streamDownload(function () use ($query, $register) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, [...ManualJournalRegisterService::COLUMNS, 'Status']);
            $no = 1;
            $query->clone()->chunkById(100, function ($entries) use ($out, $register, &$no) {
                foreach ($register->rows($entries, $no) as $row) {
                    fputcsv($out, ManualJournalRegisterService::csvRow($row));
                    $no = $row['no'] + 1;
                }
            });
            fclose($out);
        }, 'data-jurnal-manual-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, JournalEntry $entry, ManualJournalService $journals): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'posted_on' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'reason.required' => 'Alasan pembalikan wajib diisi.',
            'reason.min' => 'Alasan pembalikan minimal 3 karakter.',
        ]);

        try {
            $journals->reverse($entry, [
                'company_id' => $entry->company_id,
                'reason' => $data['reason'],
                'posted_on' => $data['posted_on'] ?? now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['manual' => $e->getMessage()]);
        }

        return back()->with('success', 'Jurnal '.$entry->reference.' dibalik.');
    }

    public function attach(Request $request, JournalEntry $entry, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeEntry($request, $entry);
        $request->validate(AttachmentService::rules() + ['attachments' => ['required', 'array', 'min:1', 'max:'.AttachmentService::MAX_FILES]]);
        DB::transaction(fn () => $attachments->attach($entry, $entry->company_id, $request->file('attachments', []), $request->user()->id));

        return back()->with('success', 'Bukti ditambahkan.');
    }
}
