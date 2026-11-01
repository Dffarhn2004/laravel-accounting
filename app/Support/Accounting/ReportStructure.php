<?php

namespace App\Support\Accounting;

/**
 * Where an account sits in the financial statements (PRD 05-LAPORAN.md #2-#5).
 *
 * Profit and loss is classified by the account CODE prefix so that accounts a user adds later
 * land in the right place without extra configuration:
 *   4xxx (not 49) Pendapatan operasional      49xx Pendapatan non-operasional
 *   51xx Direct cost (HPP, discount, kirim)   52xx Overhead (and any other 5xxx)
 *   53xx Depreciation                          54xx Beban non-operasional
 *   55xx Pajak
 */
final class ReportStructure
{
    public const PNL_ORDER = ['revenue', 'direct_cost', 'overhead', 'depreciation', 'other_income', 'other_expense', 'tax'];

    public const PNL_TITLES = [
        'revenue' => 'Pendapatan Operasional',
        'direct_cost' => 'Direct Cost',
        'overhead' => 'Overhead',
        'depreciation' => 'Depreciation',
        'other_income' => 'Pendapatan Non-Operasional',
        'other_expense' => 'Beban Non-Operasional',
        'tax' => 'Pajak',
    ];

    public static function pnlSection(string $code, string $type): string
    {
        if ($type === 'income') {
            return str_starts_with($code, '49') ? 'other_income' : 'revenue';
        }

        return match (substr($code, 0, 2)) {
            '51' => 'direct_cost',
            '53' => 'depreciation',
            '54' => 'other_expense',
            '55' => 'tax',
            default => 'overhead',
        };
    }

    /** Cash flow bucket for a non-cash balance-sheet account. */
    public static function cashFlowBucket(string $type, ?string $module, bool $contra): string
    {
        if ($module === 'Aset Tetap') {
            return $contra ? 'depreciation' : 'investing';
        }

        return in_array($module, ['Pinjaman', 'Dividen', 'Ekuitas'], true) || $type === 'equity' ? 'financing' : 'working_capital';
    }
}
