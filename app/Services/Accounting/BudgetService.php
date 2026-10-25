<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetMonthlyLine;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BudgetService
{
    public function create(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $account = Account::where('company_id', $data['company_id'])
                ->whereIn('type', ['income', 'expense'])
                ->findOrFail($data['account_id']);

            if (Budget::where('company_id', $data['company_id'])
                ->where('branch_id', $data['branch_id'])
                ->where('account_id', $account->id)
                ->where('fiscal_year', $data['fiscal_year'])
                ->where('activity', $data['activity'] ?? 'general')
                ->where('revision', $data['revision'] ?? 1)
                ->exists()) {
                throw new InvalidArgumentException('Budget revision already exists.');
            }

            $budget = Budget::create([
                ...collect($data)->except('monthly_lines')->all(),
                'activity' => $data['activity'] ?? 'general',
                'revision' => $data['revision'] ?? 1,
                'status' => 'draft',
            ]);
            $budget->lines()->createMany($data['monthly_lines'] ?? []);

            return $budget->load('monthlyLines');
        });
    }

    public function publish(Budget $budget): Budget
    {
        if ($budget->status === 'published') {
            throw new InvalidArgumentException('Budget is already published.');
        }

        $budget->update(['status' => 'published', 'published_at' => now()]);

        return $budget->fresh('monthlyLines');
    }

    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        FiscalPeriod::where('company_id', $data['company_id'])
            ->whereYear('starts_on', $data['fiscal_year'])
            ->firstOrFail();
    }
}
