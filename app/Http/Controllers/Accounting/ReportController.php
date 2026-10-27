<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request, FinancialReportService $reports)
    {
        return Inertia::render('accounting/reports', $reports->summary($request));
        /*
        $companyId = $request->integer('company_id');
        $lines = JournalLine::query()
            ->with(['account', 'journalEntry'])
            ->whereHas('journalEntry', function ($query) use ($request) {
                $query->when($request->integer('company_id'), fn ($q, $id) => $q->where('company_id', $id))
                    ->when($request->date('from'), fn ($q, $date) => $q->whereDate('posted_on', '>=', $date))
                    ->when($request->date('to'), fn ($q, $date) => $q->whereDate('posted_on', '<=', $date));
            })
            ->get();

        $summary = $lines->groupBy(fn ($line) => $line->account->type)->map(
            fn ($items) => [
                'debit' => $items->sum('debit'),
                'credit' => $items->sum('credit'),
            ],
        );
        $receivables = Invoice::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('status', ['unpaid', 'partial'])
            ->get()
            ->sum(fn ($invoice) => bcsub((string) $invoice->total, (string) $invoice->receiptAllocations()->sum('amount'), 2));
        $payables = Expense::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('status', ['open', 'partial'])
            ->get()
            ->sum(fn ($expense) => bcsub((string) $expense->amount, (string) $expense->payments()->sum('amount'), 2));

        return Inertia::render('accounting/reports', [
            'summary' => $summary,
            'receivables' => $receivables,
            'payables' => $payables,
            'cashAccounts' => CashAccount::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->with('account')
                ->get(),
        ]);
        */
    }

    public function profitLoss(Request $request, FinancialReportService $reports)
    {
        return Inertia::render('accounting/financial-statement', [
            ...$reports->summary($request),
            'statement' => 'profit-loss',
        ]);
    }

    public function balanceSheet(Request $request, FinancialReportService $reports)
    {
        return Inertia::render('accounting/financial-statement', [
            ...$reports->summary($request),
            'statement' => 'balance-sheet',
        ]);
    }
}
