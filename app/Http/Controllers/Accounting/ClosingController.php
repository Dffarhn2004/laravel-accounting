<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Models\JournalLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClosingController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $periods = FiscalPeriod::query()->where('company_id', $companyId)->orderByDesc('starts_on')->get();
        $checks = $periods->mapWithKeys(fn (FiscalPeriod $period) => [$period->id => $this->check($period)]);

        return Inertia::render('accounting/closing', compact('periods', 'checks'));
    }

    public function close(Request $request, FiscalPeriod $period): RedirectResponse
    {
        abort_unless($period->company_id === $request->user()->company_id, 404);
        abort_unless($period->status === 'open', 422, 'Period already closed.');
        $check = $this->check($period);
        abort_unless($check['balanced'], 422, 'Ledger is not balanced for this period.');
        $period->update(['status' => 'closed']);

        return back()->with('success', 'Fiscal period closed.');
    }

    private function check(FiscalPeriod $period): array
    {
        $totals = JournalLine::query()->whereHas('journalEntry', fn ($query) => $query->where('fiscal_period_id', $period->id))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();
        $debit = (float) $totals->debit;
        $credit = (float) $totals->credit;

        return ['debit' => $debit, 'credit' => $credit, 'difference' => $debit - $credit, 'balanced' => abs($debit - $credit) < 0.01];
    }
}
