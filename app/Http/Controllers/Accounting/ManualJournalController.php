<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ManualJournalRequest;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManualJournalController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        return Inertia::render('accounting/manual-journal', [
            'accounts' => \App\Models\Account::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'branches' => \App\Models\Branch::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'fiscalPeriods' => \App\Models\FiscalPeriod::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'open')
                ->get(['id', 'starts_on', 'ends_on']),
        ]);
    }

    public function store(ManualJournalRequest $request, JournalPostingService $service): RedirectResponse
    {
        $service->post($request->validated());

        return back()->with('success', 'Manual journal posted.');
    }
}
