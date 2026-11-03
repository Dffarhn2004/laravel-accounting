<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Support\Accounting\ReportStructure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laba Rugi (PRD 05-LAPORAN.md #2 per cabang, #3 konsolidasi).
 *
 * One statement shape, two column sets: twelve months of a year, or one column per branch.
 * Sections: Pendapatan Operasional, Direct Cost -> Gross Margin, Overhead -> EBITDA,
 * Depreciation -> EBIT, non-operating income/expense -> EBT, Pajak -> Laba Bersih.
 * Everything comes from journal lines; amounts are bcmath strings on the account's natural side
 * (income: credit - debit, expense: debit - credit).
 */
class ProfitLossService
{
    /** Twelve months of one year. */
    public function monthly(int $companyId, int $year, ?int $branchId, bool $showAll = false): array
    {
        $data = $this->fetch($companyId, "{$year}-01-01", "{$year}-12-31", $branchId, fn ($row) => (int) Carbon::parse($row->posted_on)->month);
        $columns = array_slice(LedgerService::MONTHS, 1);

        return ['columns' => $columns] + $this->assemble($companyId, $data, range(1, 12), $showAll);
    }

    /** One column per branch for any period; the TOTAL column equals the consolidated statement. */
    public function byBranch(int $companyId, string $from, string $to, ?int $restrictBranch, bool $showAll = false): array
    {
        $branches = Branch::where('company_id', $companyId)->when($restrictBranch, fn ($q, $id) => $q->whereKey($id))->orderBy('code')->get(['id', 'code', 'name']);
        $data = $this->fetch($companyId, $from, $to, $restrictBranch, fn ($row) => (int) $row->branch_id);

        return ['columns' => $branches->map(fn ($b) => $b->name)->all(), 'branch_ids' => $branches->pluck('id')->all()]
            + $this->assemble($companyId, $data, $branches->pluck('id')->all(), $showAll);
    }

    /**
     * @param  callable(object): int  $column  maps a fetched row to its column key
     * @return array<int, array<int, string>>  account_id => column key => natural amount
     */
    private function fetch(int $companyId, string $from, string $to, ?int $branchId, callable $column): array
    {
        $rows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)
            ->whereIn('a.type', ['income', 'expense'])
            ->whereDate('e.posted_on', '>=', $from)->whereDate('e.posted_on', '<=', $to)
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->groupBy('l.account_id', 'e.branch_id', 'e.posted_on', 'a.type')
            ->get(['l.account_id', 'e.branch_id', 'e.posted_on', 'a.type', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c')]);

        $out = [];
        foreach ($rows as $row) {
            $net = $row->type === 'income'
                ? bcsub(LedgerService::money($row->c), LedgerService::money($row->d), 2)
                : bcsub(LedgerService::money($row->d), LedgerService::money($row->c), 2);
            $key = $column($row);
            $out[$row->account_id][$key] = bcadd($out[$row->account_id][$key] ?? '0.00', $net, 2);
        }

        return $out;
    }

    /**
     * @param  array<int, array<int, string>>  $data
     * @param  array<int, int>  $keys  ordered column keys
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, string>}
     */
    public function assemble(int $companyId, array $data, array $keys, bool $showAll): array
    {
        $zero = array_fill(0, count($keys), '0.00');
        $accounts = Account::where('company_id', $companyId)->whereIn('type', ['income', 'expense'])->orderBy('code')->get(['id', 'code', 'name', 'type']);

        $rows = [];
        $totals = []; // section => values[]
        foreach (ReportStructure::PNL_ORDER as $section) {
            $totals[$section] = $zero;
        }

        $bySection = $accounts->groupBy(fn ($a) => ReportStructure::pnlSection($a->code, $a->type));
        $accountRows = [];
        foreach (ReportStructure::PNL_ORDER as $section) {
            foreach ($bySection[$section] ?? [] as $a) {
                $values = array_map(fn ($k) => $data[$a->id][$k] ?? '0.00', $keys);
                $total = array_reduce($values, fn ($c, $v) => bcadd($c, $v, 2), '0.00');
                if (! $showAll && bccomp($total, '0', 2) === 0 && ! $this->hasMovement($data[$a->id] ?? [])) {
                    continue;
                }
                $accountRows[$section][] = ['type' => 'account', 'code' => $a->code, 'label' => $a->name, 'account_id' => $a->id, 'values' => $values, 'total' => $total];
                $totals[$section] = $this->add($totals[$section], $values);
            }
        }

        $emit = function (string $section) use (&$rows, $accountRows, $totals) {
            $rows[] = ['type' => 'section', 'label' => ReportStructure::PNL_TITLES[$section]];
            foreach ($accountRows[$section] ?? [] as $r) {
                $rows[] = $r;
            }
            $rows[] = $this->line('subtotal', 'Total '.ReportStructure::PNL_TITLES[$section], $totals[$section]);
        };

        $emit('revenue');
        $emit('direct_cost');
        $grossMargin = $this->sub($totals['revenue'], $totals['direct_cost']);
        $rows[] = $this->line('total', 'Gross Margin', $grossMargin);

        $emit('overhead');
        $ebitda = $this->sub($grossMargin, $totals['overhead']);
        $rows[] = $this->line('total', 'EBITDA', $ebitda);

        $emit('depreciation');
        $ebit = $this->sub($ebitda, $totals['depreciation']);
        $rows[] = $this->line('total', 'EBIT', $ebit);

        $emit('other_income');
        $emit('other_expense');
        $ebt = $this->sub($this->add($ebit, $totals['other_income']), $totals['other_expense']);
        $rows[] = $this->line('total', 'EBT (Laba Sebelum Pajak)', $ebt);

        $emit('tax');
        $net = $this->sub($ebt, $totals['tax']);
        $rows[] = $this->line('total', 'Laba Bersih', $net);

        // % Rasio = share of operating revenue; N/A when there is no revenue.
        $revenueTotal = $this->sumValues($totals['revenue']);
        foreach ($rows as &$row) {
            if (isset($row['total'])) {
                $row['ratio'] = bccomp($revenueTotal, '0', 2) === 0 ? null : bcdiv(bcmul($row['total'], '100', 4), $revenueTotal, 2);
            }
        }
        unset($row);

        return [
            'rows' => $rows,
            'summary' => [
                'revenue' => $revenueTotal,
                'gross_margin' => $this->sumValues($grossMargin),
                'ebitda' => $this->sumValues($ebitda),
                'ebit' => $this->sumValues($ebit),
                'ebt' => $this->sumValues($ebt),
                'net_income' => $this->sumValues($net),
            ],
        ];
    }

    private function hasMovement(array $byColumn): bool
    {
        return (bool) array_filter($byColumn, fn ($v) => bccomp($v, '0', 2) !== 0);
    }

    private function line(string $type, string $label, array $values): array
    {
        return ['type' => $type, 'label' => $label, 'values' => $values, 'total' => $this->sumValues($values)];
    }

    private function sumValues(array $values): string
    {
        return array_reduce($values, fn ($c, $v) => bcadd($c, $v, 2), '0.00');
    }

    private function add(array $a, array $b): array
    {
        return array_map(fn ($x, $y) => bcadd($x, $y, 2), $a, $b);
    }

    private function sub(array $a, array $b): array
    {
        return array_map(fn ($x, $y) => bcsub($x, $y, 2), $a, $b);
    }
}
