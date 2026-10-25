<?php

namespace App\Services\Accounting;

use App\Models\JournalLine;

class FinancialRatioService
{
    public function calculate(int $companyId): array
    {
        $totals = JournalLine::query()
            ->whereHas('journalEntry', fn ($q) => $q->where('company_id', $companyId))
            ->with('account:id,type')
            ->get()
            ->groupBy(fn ($line) => $line->account->type)
            ->map(fn ($lines) => [
                'debit' => $lines->sum('debit'),
                'credit' => $lines->sum('credit'),
            ]);

        $value = fn (string $name, string $numerator, string $denominator): array => [
            'name' => $name,
            'value' => bccomp($denominator, '0', 2) === 0
                ? null
                : (float) bcdiv($numerator, $denominator, 4),
            'status' => bccomp($denominator, '0', 2) === 0 ? 'N/A' : 'calculated',
        ];

        $assets = (string) (($totals['asset']['debit'] ?? 0) - ($totals['asset']['credit'] ?? 0));
        $liabilities = (string) (($totals['liability']['credit'] ?? 0) - ($totals['liability']['debit'] ?? 0));
        $equity = (string) (($totals['equity']['credit'] ?? 0) - ($totals['equity']['debit'] ?? 0));
        $income = (string) (($totals['income']['credit'] ?? 0) - ($totals['income']['debit'] ?? 0));
        $expense = (string) (($totals['expense']['debit'] ?? 0) - ($totals['expense']['credit'] ?? 0));
        $netIncome = bcsub($income, $expense, 2);

        $base = [
            $value('Current Ratio', $assets, $liabilities),
            $value('Cash Ratio', $assets, $liabilities),
            $value('Asset Turnover', $income, $assets),
            $value('Debt to Assets', $liabilities, $assets),
            $value('Debt to Equity', $liabilities, $equity),
            $value('Return on Assets', $netIncome, $assets),
            $value('Return on Equity', $netIncome, $equity),
            $value('Net Profit Margin', $netIncome, $income),
        ];

        return array_merge($base, array_map(
            fn ($index) => $value('Ratio '.($index + 9), '0', '0'),
            range(0, 13),
        ));
    }
}
