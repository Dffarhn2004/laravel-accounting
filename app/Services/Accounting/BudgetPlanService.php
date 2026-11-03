<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Budget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anggaran (PRD 06): one row = cabang + akun (kode 4/5) + kegiatan, with a tarif and twelve quantities.
 * The amount of a month is always quantity x tarif; both are stored so the plan can be edited faithfully.
 * Rekap reuses the Laba Rugi statement structure; Kinerja compares the plan to the journal.
 */
class BudgetPlanService
{
    public const CSV_COLUMNS = ['Cabang', 'Kode Akun', 'Nama Akun', 'Rincian Kegiatan', 'Satuan', 'Tarif'];

    /** Achievement within this many percentage points of the target counts as "dekat target". */
    public const NEAR_POINTS = '10';

    /**
     * @param  array{branch_id:int, fiscal_year:int, account_id:int, activity:string, unit?:?string, rate:string|int|float, quantities:array<int, string|int|float>}  $data
     */
    public function save(int $companyId, array $data, ?Budget $budget = null): Budget
    {
        return DB::transaction(function () use ($companyId, $data, $budget) {
            Branch::where('company_id', $companyId)->findOrFail($data['branch_id']);
            Account::where('company_id', $companyId)->whereIn('type', ['income', 'expense'])->findOrFail($data['account_id']);
            $activity = trim((string) $data['activity']);

            $duplicate = Budget::where('company_id', $companyId)->where('branch_id', $data['branch_id'])->where('fiscal_year', $data['fiscal_year'])
                ->where('account_id', $data['account_id'])->where('activity', $activity)->where('revision', 1)
                ->when($budget, fn ($q) => $q->whereKeyNot($budget->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['activity' => 'Kegiatan ini sudah ada untuk cabang, tahun, dan akun yang sama. Ubah dari Data Anggaran.']);
            }

            $fields = [
                'branch_id' => $data['branch_id'], 'fiscal_year' => $data['fiscal_year'], 'account_id' => $data['account_id'],
                'activity' => $activity, 'unit' => ($data['unit'] ?? null) ?: null, 'rate' => LedgerService::money($data['rate']),
                'status' => 'published',
            ];
            if ($budget) {
                abort_unless($budget->company_id === $companyId, 404);
                $budget->update($fields);
            } else {
                $budget = Budget::create($fields + ['company_id' => $companyId, 'revision' => 1]);
            }

            for ($m = 1; $m <= 12; $m++) {
                $qty = LedgerService::money($data['quantities'][$m - 1] ?? 0);
                $budget->lines()->updateOrCreate(['month' => $m], ['quantity' => $qty, 'amount' => bcmul($qty, LedgerService::money($data['rate']), 2)]);
            }

            return $budget->load('lines');
        });
    }

    /**
     * Register rows (Data Anggaran).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(int $companyId, int $year, ?int $branchId, string $search = ''): array
    {
        $search = mb_strtolower(trim($search));

        return Budget::with(['account:id,code,name,type', 'branch:id,code,name', 'lines'])
            ->where('company_id', $companyId)->where('fiscal_year', $year)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->get()
            ->sortBy(fn (Budget $b) => [$b->account->code, $b->branch?->code, $b->activity])
            ->filter(fn (Budget $b) => $search === '' || str_contains(mb_strtolower($b->activity.' '.$b->account->code.' '.$b->account->name.' '.$b->branch?->name), $search))
            ->map(function (Budget $b) {
                $qty = array_fill(1, 12, '0.00');
                $amount = array_fill(1, 12, '0.00');
                foreach ($b->lines as $line) {
                    $qty[$line->month] = LedgerService::money($line->quantity);
                    $amount[$line->month] = LedgerService::money($line->amount);
                }

                return [
                    'id' => $b->id, 'branch_id' => $b->branch_id, 'branch' => $b->branch?->name, 'code' => $b->account->code, 'name' => $b->account->name,
                    'type' => $b->account->type, 'activity' => $b->activity, 'unit' => $b->unit, 'rate' => LedgerService::money($b->rate),
                    'qty' => $qty, 'amount' => $amount,
                    'total_qty' => array_reduce($qty, fn ($c, $v) => bcadd($c, $v, 2), '0.00'),
                    'total' => array_reduce($amount, fn ($c, $v) => bcadd($c, $v, 2), '0.00'),
                ];
            })->values()->all();
    }

    /**
     * Per cabang: number of rows, pendapatan, beban and laba of the plan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function branchSummary(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = $r['branch'] ?? '—';
            $out[$k] ??= ['branch' => $k, 'lines' => 0, 'income' => '0.00', 'expense' => '0.00', 'profit' => '0.00'];
            $out[$k]['lines']++;
            $side = $r['type'] === 'income' ? 'income' : 'expense';
            $out[$k][$side] = bcadd($out[$k][$side], $r['total'], 2);
            $out[$k]['profit'] = bcsub($out[$k]['income'], $out[$k]['expense'], 2);
        }
        ksort($out);

        return array_values($out);
    }

    /**
     * Rekap Anggaran: the plan shaped as the Laba Rugi matrix (Jan-Des, TOTAL, % rasio).
     *
     * @return array<string, mixed>
     */
    public function recap(int $companyId, int $year, ?int $branchId, bool $showAll = false): array
    {
        $data = [];
        $lines = DB::table('budget_monthly_lines as l')->join('budgets as b', 'b.id', '=', 'l.budget_id')
            ->where('b.company_id', $companyId)->where('b.fiscal_year', $year)
            ->when($branchId, fn ($q, $id) => $q->where('b.branch_id', $id))
            ->get(['b.account_id', 'l.month', 'l.amount']);
        foreach ($lines as $l) {
            $data[$l->account_id][(int) $l->month] = bcadd($data[$l->account_id][(int) $l->month] ?? '0.00', LedgerService::money($l->amount), 2);
        }

        $report = app(ProfitLossService::class)->assemble($companyId, $data, range(1, 12), $showAll);
        $report['columns'] = array_slice(LedgerService::MONTHS, 1);
        $report['summary']['expense'] = bcsub($report['summary']['revenue'], $report['summary']['net_income'], 2);
        $report['summary']['net_margin'] = bccomp($report['summary']['revenue'], '0', 2) === 0 ? null : bcdiv(bcmul($report['summary']['net_income'], '100', 4), $report['summary']['revenue'], 2);

        return $report;
    }

    /**
     * Kinerja: plan vs journal between two months (inclusive), per account.
     *
     * @return array{rows:array<int, array<string, mixed>>, kpi:array<string, array<string, mixed>>}
     */
    public function performance(int $companyId, string $from, string $to, ?int $branchId): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();

        $planned = [];
        $lines = DB::table('budget_monthly_lines as l')->join('budgets as b', 'b.id', '=', 'l.budget_id')
            ->where('b.company_id', $companyId)
            ->whereBetween('b.fiscal_year', [$start->year, $end->year])
            ->when($branchId, fn ($q, $id) => $q->where('b.branch_id', $id))
            ->get(['b.account_id', 'b.fiscal_year', 'l.month', 'l.amount']);
        foreach ($lines as $l) {
            $day = Carbon::create((int) $l->fiscal_year, (int) $l->month, 1);
            if ($day->betweenIncluded($start, $end)) {
                $planned[$l->account_id] = bcadd($planned[$l->account_id] ?? '0.00', LedgerService::money($l->amount), 2);
            }
        }

        $actual = [];
        $moves = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)->whereIn('a.type', ['income', 'expense'])
            ->whereDate('e.posted_on', '>=', $start->toDateString())->whereDate('e.posted_on', '<=', $end->toDateString())
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->groupBy('l.account_id', 'a.type')
            ->get(['l.account_id', 'a.type', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c')]);
        foreach ($moves as $m) {
            $actual[$m->account_id] = $m->type === 'income'
                ? bcsub(LedgerService::money($m->c), LedgerService::money($m->d), 2)
                : bcsub(LedgerService::money($m->d), LedgerService::money($m->c), 2);
        }

        $accounts = Account::where('company_id', $companyId)->whereIn('type', ['income', 'expense'])->orderBy('code')->get(['id', 'code', 'name', 'type'])
            ->filter(fn ($a) => isset($planned[$a->id]) || (isset($actual[$a->id]) && bccomp($actual[$a->id], '0', 2) !== 0));

        $rows = [];
        $sum = ['income' => ['budget' => '0.00', 'actual' => '0.00'], 'expense' => ['budget' => '0.00', 'actual' => '0.00']];
        foreach ($accounts as $a) {
            $budget = $planned[$a->id] ?? '0.00';
            $real = $actual[$a->id] ?? '0.00';
            $sum[$a->type]['budget'] = bcadd($sum[$a->type]['budget'], $budget, 2);
            $sum[$a->type]['actual'] = bcadd($sum[$a->type]['actual'], $real, 2);
            $rows[] = ['code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'account_id' => $a->id, 'budget' => $budget, 'actual' => $real] + $this->achievement($budget, $real, $a->type === 'expense');
        }

        $profitBudget = bcsub($sum['income']['budget'], $sum['expense']['budget'], 2);
        $profitActual = bcsub($sum['income']['actual'], $sum['expense']['actual'], 2);

        return [
            'rows' => $rows,
            'kpi' => [
                'income' => ['label' => 'Pencapaian pendapatan', 'budget' => $sum['income']['budget'], 'actual' => $sum['income']['actual']] + $this->achievement($sum['income']['budget'], $sum['income']['actual'], false),
                'expense' => ['label' => 'Penyerapan beban', 'budget' => $sum['expense']['budget'], 'actual' => $sum['expense']['actual']] + $this->achievement($sum['expense']['budget'], $sum['expense']['actual'], true),
                'profit' => ['label' => 'Pencapaian laba', 'budget' => $profitBudget, 'actual' => $profitActual] + $this->achievement($profitBudget, $profitActual, false),
            ],
        ];
    }

    /**
     * Achievement % and status. Target 0 gives N/A (never a division by zero).
     * Pendapatan/laba: hijau >= 100, kuning within NEAR_POINTS below, merah otherwise.
     * Beban: hijau <= 100, kuning up to NEAR_POINTS above, merah otherwise.
     *
     * @return array{achievement:?string, status:string}
     */
    public function achievement(string $budget, string $actual, bool $lowerIsBetter): array
    {
        if (bccomp($budget, '0', 2) <= 0) {
            return ['achievement' => null, 'status' => 'na'];
        }
        $pct = bcdiv(bcmul($actual, '100', 4), $budget, 2);
        $near = self::NEAR_POINTS;
        $status = $lowerIsBetter
            ? (bccomp($pct, '100', 2) <= 0 ? 'good' : (bccomp($pct, bcadd('100', $near, 2), 2) <= 0 ? 'near' : 'bad'))
            : (bccomp($pct, '100', 2) >= 0 ? 'good' : (bccomp($pct, bcsub('100', $near, 2), 2) >= 0 ? 'near' : 'bad'));

        return ['achievement' => $pct, 'status' => $status];
    }
}
