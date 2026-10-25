<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;

class HealthScoreService
{
    public function calculate(int $companyId, ?int $branchId = null): array
    {
        $entries = JournalEntry::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->with('lines')
            ->get();
        $balanced = $entries->every(
            fn ($entry) => bccomp(
                (string) $entry->lines->sum('debit'),
                (string) $entry->lines->sum('credit'),
                2,
            ) === 0,
        );

        return [
            'score' => $balanced && $entries->isNotEmpty() ? 100 : null,
            'status' => $balanced && $entries->isNotEmpty() ? 'healthy' : 'insufficient_data',
            'checks' => [
                'journal_balance' => $balanced,
                'journal_count' => $entries->count(),
            ],
        ];
    }
}
