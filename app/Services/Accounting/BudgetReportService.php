<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\JournalLine;
use Illuminate\Support\Collection;

class BudgetReportService
{
    public function achievement(Budget $budget): array
    {
        $budget->loadMissing('monthlyLines');
        $budgetAmount = (float) $budget->monthlyLines->sum('amount');
        $actual = (float) JournalLine::query()
            ->where('account_id', $budget->account_id)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('company_id', $budget->company_id)
                ->where('branch_id', $budget->branch_id)
                ->whereYear('posted_on', $budget->fiscal_year))
            ->get()
            ->sum(fn ($line) => $budget->account->type === 'income'
                ? (float) $line->credit - (float) $line->debit
                : (float) $line->debit - (float) $line->credit);

        return [
            'budget' => $budgetAmount,
            'actual' => $actual,
            'achievement' => $budgetAmount === 0.0 ? null : number_format($actual / $budgetAmount * 100, 2, '.', ''),
        ];
    }

    public function compare(array $filters = []): Collection
    {
        return Budget::query()
            ->with(['account', 'monthlyLines'])
            ->where('status', 'published')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['fiscal_year'] ?? null, fn ($q, $year) => $q->where('fiscal_year', $year))
            ->get()
            ->map(fn (Budget $budget) => [
                'budget_id' => $budget->id,
                'account' => $budget->account->code,
                'activity' => $budget->activity,
                'budget' => $budget->monthlyLines->sum('amount'),
                'actual' => $this->achievement($budget)['actual'],
                'achievement' => $this->achievement($budget)['achievement'],
            ]);
    }
}
