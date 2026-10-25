<?php

namespace App\Services\Accounting;

use App\Models\JournalLine;
use App\Models\Expense;
use App\Models\Invoice;

class FinancialReportService
{
    public function kpis(\Illuminate\Http\Request $request): array
    {
        $summary = $this->summarize(
            $request->user()?->company_id,
            $request->user()?->branch_id,
        );

        $income = (float) ($summary['income']['credit'] ?? 0) - (float) ($summary['income']['debit'] ?? 0);
        $expense = (float) ($summary['expense']['debit'] ?? 0) - (float) ($summary['expense']['credit'] ?? 0);

        return [
            'summary' => $summary,
            'metrics' => [
                'Pendapatan' => number_format($income, 2, '.', ','),
                'Laba (Rugi) Bersih' => number_format($income - $expense, 2, '.', ','),
                'Kas & Setara Kas' => number_format((float) ($summary['asset']['balance'] ?? 0), 2, '.', ','),
                'Total Aset' => number_format((float) ($summary['asset']['balance'] ?? 0), 2, '.', ','),
                'Piutang Outstanding' => number_format((float) Invoice::query()
                    ->where('company_id', $request->user()?->company_id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->get()
                    ->sum(fn ($invoice) => (float) $invoice->total - (float) $invoice->receiptAllocations()->sum('amount')), 2, '.', ','),
                'Hutang Outstanding' => number_format((float) Expense::query()
                    ->where('company_id', $request->user()?->company_id)
                    ->whereIn('status', ['open', 'partial'])
                    ->get()
                    ->sum(fn ($expense) => (float) $expense->amount - (float) $expense->payments()->sum('amount')), 2, '.', ','),
            ],
        ];
    }

    public function summary(\Illuminate\Http\Request $request): array
    {
        $companyId = $request->user()?->company_id;
        $branchId = $request->user()?->branch_id;

        return ['summary' => $this->summarize(
            $companyId,
            $branchId,
        ), 'receivables' => Invoice::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['unpaid', 'partial'])
            ->get()
            ->sum(fn ($invoice) => (float) $invoice->total - (float) $invoice->receiptAllocations()->sum('amount')),
        'payables' => Expense::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['open', 'partial'])
            ->get()
            ->sum(fn ($expense) => (float) $expense->amount - (float) $expense->payments()->sum('amount'))];
    }

    public function summarize(?int $companyId = null, ?int $branchId = null): array
    {
        $lines = JournalLine::query()
            ->with('account')
            ->whereHas('journalEntry', fn ($query) => $query
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
            ->get();

        return $lines->groupBy(fn ($line) => $line->account->type)->map(fn ($items) => [
            'debit' => $items->sum('debit'),
            'credit' => $items->sum('credit'),
            'balance' => $items->sum(fn ($line) => (float) $line->debit - (float) $line->credit),
        ])->all();
    }
}
