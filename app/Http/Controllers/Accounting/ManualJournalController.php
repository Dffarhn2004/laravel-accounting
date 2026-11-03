<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ManualJournalRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\ManualJournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ManualJournalController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        $companyId = $user?->company_id;

        return Inertia::render('accounting/manual-journal', [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->when($user?->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function store(ManualJournalRequest $request, ManualJournalService $journals, AttachmentService $attachments): RedirectResponse
    {
        abort_unless($request->user()->company_id, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments'], $data['company_id']);

        try {
            $entry = DB::transaction(function () use ($request, $journals, $attachments, $data, $files) {
                $existed = JournalEntry::where('company_id', $request->user()->company_id)->where('idempotency_key', $data['idempotency_key'])->exists();
                $entry = $journals->record($data + [
                    'company_id' => $request->user()->company_id,
                    'created_by' => $request->user()->id,
                ]);
                if (! $existed && $files !== []) {
                    $attachments->attach($entry, $request->user()->company_id, $files, $request->user()->id);
                }

                return $entry;
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        return redirect()->route('data.jm.index')->with('success', "Jurnal {$entry->reference} berhasil disimpan.");
    }
}
