<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\OpeningBalance;
use App\Services\Accounting\TreasuryPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpeningBalanceController extends Controller
{
    public function create(Request $request)
    {
        $companyId = $request->user()->company_id;

        return Inertia::render('accounting/opening-balances', [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'openings' => OpeningBalance::query()->where('company_id', $companyId)->with(['branch', 'fiscalPeriod', 'journalEntry'])->latest('posted_on')->get(),
        ]);
    }

    public function store(Request $request, TreasuryPostingService $service): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'posted_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:150'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $service->openingBalance($data + ['company_id' => $request->user()->company_id]);

        return back()->with('success', 'Opening balance posted.');
    }
}
