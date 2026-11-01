<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Neraca (PRD 05-LAPORAN.md #4).
 *
 * Position = every journal line up to the date. Two columns: the opening position and the
 * position at the end of the period, plus growth. Opening-balance journals dated inside the
 * period count as the opening position (they are the starting point, not activity).
 *
 * Profit and loss accounts are not closed to equity by a journal, so the statement adds:
 *   Laba Ditahan (akumulasi)  = net income of all years before the year of the date
 *   Laba Tahun Berjalan       = net income from 1 Jan of that year to the date
 * which keeps Aktiva = Kewajiban + Ekuitas without touching the ledger.
 */
class BalanceSheetService
{
    /**
     * Net (debit - credit) per balance-sheet account.
     *
     * @return array<int, string>
     */
    public function nets(int $companyId, ?int $branchId, ?string $upTo, ?string $before = null, ?string $openingThrough = null): array
    {
        $q = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)
            ->whereIn('a.type', ['asset', 'liability', 'equity'])
            ->when($branchId, fn ($w, $id) => $w->where('e.branch_id', $id))
            ->when($upTo, fn ($w, $d) => $w->whereDate('e.posted_on', '<=', $d));

        if ($before !== null) {
            // Opening position: everything before the period, plus opening journals dated in it.
            $q->where(fn ($w) => $w->whereDate('e.posted_on', '<', $before)
                ->orWhere(fn ($o) => $o->where('e.source_kind', 'opening')->whereDate('e.posted_on', '<=', $openingThrough ?? $before)));
        }

        $out = [];
        foreach ($q->groupBy('l.account_id')->get(['l.account_id', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c')]) as $row) {
            $out[(int) $row->account_id] = bcsub(LedgerService::money($row->d), LedgerService::money($row->c), 2);
        }

        return $out;
    }

    /** Net income (credit - debit of income and expense accounts) between two dates, inclusive. */
    public function netIncome(int $companyId, ?int $branchId, ?string $from, string $to): string
    {
        $row = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)
            ->whereIn('a.type', ['income', 'expense'])
            ->when($branchId, fn ($w, $id) => $w->where('e.branch_id', $id))
            ->when($from, fn ($w, $d) => $w->whereDate('e.posted_on', '>=', $d))
            ->whereDate('e.posted_on', '<=', $to)
            ->first([DB::raw('COALESCE(SUM(l.credit),0) as c'), DB::raw('COALESCE(SUM(l.debit),0) as d')]);

        return bcsub(LedgerService::money($row->c), LedgerService::money($row->d), 2);
    }

    /**
     * Earnings not yet closed into equity, split at the start of the fiscal year of `$yearOf`
     * (defaults to `$asOf`). The opening column passes the period start as `$yearOf`, so profit of
     * the year that just ended is already "Laba Ditahan" on the first day of the new year.
     *
     * @return array{prior:string, current:string}
     */
    public function earnings(int $companyId, ?int $branchId, string $asOf, ?string $yearOf = null): array
    {
        $yearStart = Carbon::parse($yearOf ?? $asOf)->startOfYear()->toDateString();

        return [
            'prior' => $this->netIncome($companyId, $branchId, null, Carbon::parse($yearStart)->subDay()->toDateString()),
            'current' => $this->netIncome($companyId, $branchId, $yearStart, $asOf),
        ];
    }

    /** @return array<string, mixed> */
    public function report(int $companyId, string $from, string $to, ?int $branchId, bool $showAll = false): array
    {
        $start = $this->nets($companyId, $branchId, $to, $from, $to);
        $end = $this->nets($companyId, $branchId, $to);
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();
        $startEarn = $this->earnings($companyId, $branchId, $dayBefore, $from);
        $endEarn = $this->earnings($companyId, $branchId, $to);

        $accounts = Account::where('company_id', $companyId)->whereIn('type', ['asset', 'liability', 'equity'])->orderBy('code')->get(['id', 'code', 'name', 'type', 'module', 'is_contra']);

        // Natural position: assets debit - credit; liabilities and equity credit - debit.
        $natural = fn (Account $a, array $nets) => $a->type === 'asset' ? ($nets[$a->id] ?? '0.00') : bcmul($nets[$a->id] ?? '0.00', '-1', 2);

        $rows = [];
        $totals = ['asset' => ['0.00', '0.00'], 'liability' => ['0.00', '0.00'], 'equity' => ['0.00', '0.00']];

        $block = function (string $type, string $heading) use (&$rows, &$totals, $accounts, $natural, $start, $end, $showAll) {
            $rows[] = ['type' => 'heading', 'label' => $heading];
            foreach ($accounts->where('type', $type)->groupBy(fn ($a) => $a->module ?: 'Lainnya') as $module => $group) {
                $groupRows = [];
                $sub = ['0.00', '0.00'];
                foreach ($group as $a) {
                    $values = [$natural($a, $start), $natural($a, $end)];
                    if (! $showAll && bccomp($values[0], '0', 2) === 0 && bccomp($values[1], '0', 2) === 0) {
                        continue;
                    }
                    $groupRows[] = ['type' => 'account', 'code' => $a->code, 'label' => $a->name, 'account_id' => $a->id, 'values' => $values, 'growth' => $this->growth($values[0], $values[1])];
                    $sub = [bcadd($sub[0], $values[0], 2), bcadd($sub[1], $values[1], 2)];
                }
                if ($groupRows) {
                    $rows[] = ['type' => 'section', 'label' => $module];
                    array_push($rows, ...$groupRows);
                    $rows[] = ['type' => 'subtotal', 'label' => 'Total '.$module, 'values' => $sub, 'growth' => $this->growth($sub[0], $sub[1])];
                    $totals[$type] = [bcadd($totals[$type][0], $sub[0], 2), bcadd($totals[$type][1], $sub[1], 2)];
                }
            }
        };

        $block('asset', 'AKTIVA');
        $rows[] = $this->total('TOTAL AKTIVA', $totals['asset']);

        $block('liability', 'PASIVA — Kewajiban');
        $rows[] = $this->total('TOTAL KEWAJIBAN', $totals['liability'], 'subtotal');

        $block('equity', 'PASIVA — Ekuitas');
        $computed = [
            ['Laba Ditahan s.d. akhir tahun lalu (belum ditutup)', [$startEarn['prior'], $endEarn['prior']]],
            ['Laba Tahun Berjalan', [$startEarn['current'], $endEarn['current']]],
        ];
        foreach ($computed as [$label, $values]) {
            if (! $showAll && bccomp($values[0], '0', 2) === 0 && bccomp($values[1], '0', 2) === 0) {
                continue;
            }
            $rows[] = ['type' => 'account', 'code' => null, 'label' => $label, 'account_id' => null, 'values' => $values, 'growth' => $this->growth($values[0], $values[1]), 'computed' => true];
            $totals['equity'] = [bcadd($totals['equity'][0], $values[0], 2), bcadd($totals['equity'][1], $values[1], 2)];
        }
        $rows[] = $this->total('TOTAL EKUITAS', $totals['equity'], 'subtotal');

        $pasiva = [bcadd($totals['liability'][0], $totals['equity'][0], 2), bcadd($totals['liability'][1], $totals['equity'][1], 2)];
        $rows[] = $this->total('TOTAL PASIVA', $pasiva);

        $difference = [bcsub($totals['asset'][0], $pasiva[0], 2), bcsub($totals['asset'][1], $pasiva[1], 2)];

        return [
            'columns' => ['Saldo awal', 'Posisi akhir'],
            'rows' => $rows,
            'balance' => ['aktiva' => $totals['asset'], 'pasiva' => $pasiva, 'difference' => $difference, 'balanced' => $difference === ['0.00', '0.00']],
        ];
    }

    /** Percent change from the opening to the closing position; null (N/A) when the opening is zero. */
    private function growth(string $start, string $end): ?string
    {
        if (bccomp($start, '0', 2) === 0) {
            return null;
        }

        return bcdiv(bcmul(bcsub($end, $start, 2), '100', 4), ltrim($start, '-'), 2);
    }

    private function total(string $label, array $values, string $type = 'total'): array
    {
        return ['type' => $type, 'label' => $label, 'values' => $values, 'growth' => $this->growth($values[0], $values[1])];
    }
}
