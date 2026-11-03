<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\FixedAsset;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard (PRD 07-DASHBOARD.md). Every figure is read from the journal through the report
 * services, never from a separate calculation, so the dashboard cannot disagree with the statements.
 *
 * KPIs: Pendapatan and Laba Bersih for the period; Kas, Total Aset, Piutang and Hutang as balances
 * at the period end. "Kas" is only cash/bank accounts. Not available yet and therefore not shown
 * as numbers: the 19-ratio health score, key ratios, budget achievement and the dividend
 * projection (Langkah 7), instead of inventing placeholder values.
 *
 * The eight integrity controls compare independent sources. Status: ok, warn (explainable
 * difference such as opening balances without a document-level breakdown) or fail (the books are
 * wrong).
 */
class DashboardService
{
    public function __construct(
        private ProfitLossService $profitLoss,
        private BalanceSheetService $balanceSheet,
        private CashFlowService $cashFlow,
    ) {}

    /**
     * @param  ?int  $scope  branch applied to every figure (a bound user's branch, else the filter)
     * @param  ?int  $bound  the user's own branch, which also limits the branch comparison
     */
    public function build(int $companyId, string $from, string $to, ?int $scope, ?int $bound): array
    {
        $year = Carbon::parse($to)->year;
        $accounts = Account::where('company_id', $companyId)->get(['id', 'code', 'name', 'type', 'module', 'is_contra'])->keyBy('id');
        $nets = $this->balanceSheet->nets($companyId, $scope, $to);
        $natural = fn (Account $a) => $a->type === 'asset' ? ($nets[$a->id] ?? '0.00') : bcmul($nets[$a->id] ?? '0.00', '-1', 2);
        $sumWhere = fn (callable $filter) => $accounts->filter($filter)->reduce(fn ($c, $a) => bcadd($c, $natural($a), 2), '0.00');

        $cashIds = CashAccount::where('company_id', $companyId)->pluck('account_id')->all();
        $isCash = fn (Account $a) => $a->module === 'Kas & Bank' || in_array($a->id, $cashIds, true);

        $period = $this->profitLoss->byBranch($companyId, $from, $to, $scope);
        $statement = $this->balanceSheet->report($companyId, $from, $to, $scope);
        $monthly = $this->profitLoss->monthly($companyId, $year, $scope);
        $flow = $this->cashFlow->report($companyId, $from, $to, $scope)['summary'];

        $kpis = [
            'revenue' => $period['summary']['revenue'],
            'net_income' => $period['summary']['net_income'],
            'cash' => $sumWhere($isCash),
            'total_assets' => $statement['balance']['aktiva'][1],
            'receivable' => $sumWhere(fn ($a) => $a->type === 'asset' && $a->module === 'Piutang'),
            'payable' => $sumWhere(fn ($a) => $a->type === 'liability' && $a->module === 'Hutang'),
        ];

        $row = fn (array $rows, string $label) => collect($rows)->firstWhere('label', $label);
        $monthRow = fn (string $label) => $row($monthly['rows'], $label)['values'] ?? array_fill(0, 12, '0.00');
        $revenue = $monthRow('Total Pendapatan Operasional');
        $otherIncome = $monthRow('Total Pendapatan Non-Operasional');
        $net = $monthRow('Laba Bersih');

        return [
            'kpis' => $kpis,
            'trend' => [
                'year' => $year,
                'months' => array_slice(LedgerService::MONTHS, 1),
                'revenue' => $revenue,
                'expense' => array_map(fn ($r, $o, $n) => bcsub(bcadd($r, $o, 2), $n, 2), $revenue, $otherIncome, $net),
                'profit' => $net,
                'cash' => $this->cashTrend($companyId, $scope, $year, $isCash, $accounts),
            ],
            'cash_flow' => ['operating' => $flow['operating'], 'investing' => $flow['investing'], 'financing' => $flow['financing'], 'increase' => $flow['increase']],
            'expense_composition' => collect(['Total Direct Cost', 'Total Overhead', 'Total Depreciation', 'Total Beban Non-Operasional', 'Total Pajak'])
                ->map(fn ($label) => ['label' => str_replace('Total ', '', $label), 'amount' => $row($period['rows'], $label)['total'] ?? '0.00'])->all(),
            'asset_composition' => $this->assetComposition($statement['rows']),
            'branches' => $this->branchPerformance($companyId, $from, $to, $bound),
            'matrix' => collect(['Total Pendapatan Operasional', 'Gross Margin', 'EBITDA', 'EBIT', 'EBT (Laba Sebelum Pajak)', 'Laba Bersih'])
                ->map(fn ($label) => ['label' => $label, 'values' => $monthRow($label)])->all(),
            'top_receivables' => $this->topReceivables($companyId, $scope, $to),
            'top_payables' => $this->topPayables($companyId, $scope, $to),
            'integrity' => $this->integrity($companyId, $from, $to, $scope, $bound, $accounts, $nets, $statement, $period, $flow),
            // Honest gaps: shown as "belum tersedia", never as made-up numbers.
            'unavailable' => [
                ['key' => 'health', 'title' => 'Health score (19 rasio)', 'step' => 'Analisis Rasio & Kesehatan Bisnis', 'href' => '/laporan/rasio'],
                ['key' => 'ratios', 'title' => 'Enam rasio kunci', 'step' => 'Analisis Rasio & Kesehatan Bisnis', 'href' => '/laporan/rasio'],
                ['key' => 'budget', 'title' => 'Achievement anggaran', 'step' => 'Laporan Kinerja Anggaran', 'href' => '/anggaran/ang-kinerja'],
                ['key' => 'dividend', 'title' => 'Proyeksi dividen', 'step' => 'Bagi Hasil & Dividen', 'href' => '/dividen'],
            ],
        ];
    }

    /** Cash balance at the end of each month of the year (the balance carried in, plus movements). */
    private function cashTrend(int $companyId, ?int $branch, int $year, callable $isCash, $accounts): array
    {
        $ids = $accounts->filter($isCash)->keys()->all();
        if (! $ids) {
            return array_fill(0, 12, '0.00');
        }

        $base = fn ($q) => DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)->whereIn('l.account_id', $ids)->when($branch, fn ($w, $id) => $w->where('e.branch_id', $id));

        $carried = LedgerService::money($base(null)->whereDate('e.posted_on', '<', "{$year}-01-01")->selectRaw('COALESCE(SUM(l.debit - l.credit),0) as n')->value('n'));
        $perMonth = array_fill(1, 12, '0.00');
        foreach ($base(null)->whereDate('e.posted_on', '>=', "{$year}-01-01")->whereDate('e.posted_on', '<=', "{$year}-12-31")->groupBy('e.posted_on')->get(['e.posted_on', DB::raw('SUM(l.debit - l.credit) as n')]) as $r) {
            $m = Carbon::parse($r->posted_on)->month;
            $perMonth[$m] = bcadd($perMonth[$m], LedgerService::money($r->n), 2);
        }

        $out = [];
        foreach (range(1, 12) as $m) {
            $carried = bcadd($carried, $perMonth[$m], 2);
            $out[] = $carried;
        }

        return $out;
    }

    private function assetComposition(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (($r['label'] ?? '') === 'TOTAL AKTIVA') {
                break;
            }
            if (($r['type'] ?? '') === 'subtotal') {
                $out[] = ['label' => preg_replace('/^Total /', '', $r['label']), 'amount' => $r['values'][1]];
            }
        }

        return $out;
    }

    private function branchPerformance(int $companyId, string $from, string $to, ?int $bound): array
    {
        $report = $this->profitLoss->byBranch($companyId, $from, $to, $bound);
        $revenue = collect($report['rows'])->firstWhere('label', 'Total Pendapatan Operasional')['values'] ?? [];
        $net = collect($report['rows'])->firstWhere('label', 'Laba Bersih')['values'] ?? [];

        return collect($report['columns'])->map(fn ($name, $i) => [
            'branch_id' => $report['branch_ids'][$i], 'name' => $name, 'revenue' => $revenue[$i] ?? '0.00', 'net_income' => $net[$i] ?? '0.00',
        ])->all();
    }

    /** Open invoices by outstanding amount as of the period end (receipts after it do not count). */
    private function receivableRows(int $companyId, ?int $branch, string $to)
    {
        return Invoice::query()->where('company_id', $companyId)->where('status', '!=', 'reversed')
            ->when($branch, fn ($q, $id) => $q->where('branch_id', $id))->whereDate('issued_on', '<=', $to)
            ->with('customer:id,name')
            ->withSum(['receiptAllocations as paid' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->whereDate('received_on', '<=', $to))], 'amount')
            ->get()
            ->map(fn (Invoice $i) => ['id' => $i->id, 'number' => $i->invoice_number, 'party' => $i->customer?->name, 'date' => $i->issued_on->toDateString(), 'amount' => bcsub((string) $i->total, (string) ($i->paid ?? 0), 2)])
            ->filter(fn ($r) => bccomp($r['amount'], '0', 2) > 0);
    }

    private function topReceivables(int $companyId, ?int $branch, string $to): array
    {
        return $this->receivableRows($companyId, $branch, $to)->sortByDesc(fn ($r) => (float) $r['amount'])->take(5)->values()->all();
    }

    private function topPayables(int $companyId, ?int $branch, string $to): array
    {
        return app(PayableReportService::class)->top($companyId, $branch, $to);
    }

    /** @return array{status:string, summary:string, controls: array<int, array<string, mixed>>} */
    private function integrity(int $companyId, string $from, string $to, ?int $scope, ?int $bound, $accounts, array $nets, array $statement, array $period, array $flow): array
    {
        $entryCount = DB::table('journal_entries')->where('company_id', $companyId)->when($scope, fn ($q, $id) => $q->where('branch_id', $id))->count();
        $controls = [];
        $add = function (string $key, string $title, string $status, string $detail, ?string $href = null) use (&$controls) {
            $controls[] = ['key' => $key, 'title' => $title, 'status' => $status, 'detail' => $detail, 'href' => $href];
        };
        $idr = fn (string $v) => 'Rp '.number_format((float) $v, 2, ',', '.');

        // 1. Every journal entry balances.
        $unbalanced = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)->when($scope, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->groupBy('l.journal_entry_id')->havingRaw('ROUND(SUM(l.debit), 2) <> ROUND(SUM(l.credit), 2)')->select('l.journal_entry_id')->get()->count();
        $add('journal', 'Setiap jurnal seimbang (debit = kredit)', $unbalanced === 0 ? 'ok' : 'fail', $unbalanced === 0 ? "{$entryCount} jurnal diperiksa" : "{$unbalanced} jurnal tidak seimbang", '/accounting/journals');

        // 2. Balance sheet balances.
        $diff = $statement['balance']['difference'][1];
        $add('balance_sheet', 'Neraca seimbang (Aktiva = Pasiva)', $statement['balance']['balanced'] ? 'ok' : 'fail', $statement['balance']['balanced'] ? 'Aktiva '.$idr($statement['balance']['aktiva'][1]) : 'Selisih '.$idr($diff), '/reports/balance-sheet');

        // 3. Each branch balances on its own.
        $bad = [];
        foreach (Branch::where('company_id', $companyId)->when($bound, fn ($q, $id) => $q->whereKey($id))->get(['id', 'name']) as $b) {
            if (! $this->balanceSheet->report($companyId, $from, $to, $b->id)['balance']['balanced']) {
                $bad[] = $b->name;
            }
        }
        $add('branches', 'Neraca tiap cabang seimbang', $bad ? 'fail' : 'ok', $bad ? 'Tidak seimbang: '.implode(', ', $bad) : 'Semua cabang seimbang', '/reports/branch-profit-loss');

        // 4. Cash flow ends at the balance-sheet cash.
        $add('cash_flow', 'Kas akhir Cashflow = Kas Neraca', $flow['reconciled'] ? 'ok' : 'fail', $flow['reconciled'] ? 'Kas akhir '.$idr($flow['end_cash']) : 'Selisih '.$idr(bcsub($flow['end_cash'], $flow['balance_sheet_cash'], 2)), '/reports/cash-flow');

        // 5. Profit and loss net income = current-year earnings on the balance sheet.
        $yearStart = Carbon::parse($to)->startOfYear()->toDateString();
        $plNet = $this->profitLoss->byBranch($companyId, $yearStart, $to, $scope)['summary']['net_income'];
        $bsNet = $this->balanceSheet->earnings($companyId, $scope, $to)['current'];
        $add('earnings', 'Laba Rugi = Laba Tahun Berjalan di Neraca', bccomp($plNet, $bsNet, 2) === 0 ? 'ok' : 'fail', 'Laba Rugi '.$idr($plNet).' · Neraca '.$idr($bsNet), '/reports/profit-loss');

        // 6. Receivable subledger vs GL.
        $arAccounts = Invoice::where('company_id', $companyId)->distinct()->pluck('receivable_account_id');
        $gl = $arAccounts->reduce(fn ($c, $id) => bcadd($c, $nets[$id] ?? '0.00', 2), '0.00');
        $sub = $this->receivableRows($companyId, $scope, $to)->reduce(fn ($c, $r) => bcadd($c, $r['amount'], 2), '0.00');
        $add('receivable', 'Piutang per invoice = Piutang di Buku Besar', bccomp($gl, $sub, 2) === 0 ? 'ok' : 'warn',
            'Buku besar '.$idr($gl).' · invoice '.$idr($sub).(bccomp($gl, $sub, 2) === 0 ? '' : ' (selisih dapat berasal dari saldo awal yang belum dirinci per invoice)'), '/accounting/aging');

        // 7. Payable subledger vs GL.
        $payable = app(PayableReportService::class)->build(['company_id' => $companyId, 'branch_id' => $scope, 'as_of' => $to, 'status' => 'all']);
        $glAp = $payable['reconciliation']['ledger'];
        $subAp = $payable['reconciliation']['documents'];
        $add('payable', 'Hutang per dokumen = Hutang di Buku Besar', bccomp($glAp, $subAp, 2) === 0 ? 'ok' : 'warn',
            'Buku besar '.$idr($glAp).' · dokumen '.$idr($subAp).(bccomp($payable['reconciliation']['undocumented'], '0', 2) === 0 ? '' : ' (selisih '.$idr($payable['reconciliation']['undocumented']).' tanpa dokumen)'), '/laporan/lap-hutang');

        // 8. Fixed asset register vs GL.
        $assetAccounts = FixedAsset::where('company_id', $companyId)->distinct()->pluck('asset_account_id');
        $glAsset = $assetAccounts->reduce(fn ($c, $id) => bcadd($c, $nets[$id] ?? '0.00', 2), '0.00');
        $register = (string) FixedAsset::where('company_id', $companyId)->when($scope, fn ($q, $id) => $q->where('branch_id', $id))->where('status', 'active')->sum('cost');
        $register = bcadd($register, '0', 2);
        $add('assets', 'Register aset = Aset tetap di Buku Besar', bccomp($glAsset, $register, 2) === 0 ? 'ok' : 'warn',
            'Buku besar '.$idr($glAsset).' · register '.$idr($register).(bccomp($glAsset, $register, 2) === 0 ? '' : ' (selisih berasal dari jurnal manual pada akun aset)'), '/laporan/lap-asset');

        $statuses = array_column($controls, 'status');
        $status = $entryCount === 0 ? 'empty' : (in_array('fail', $statuses, true) ? 'invalid' : (in_array('warn', $statuses, true) ? 'attention' : 'valid'));

        return [
            'status' => $status,
            'summary' => match ($status) {
                'empty' => 'Belum ada transaksi. Kontrol berjalan setelah ada jurnal.',
                'invalid' => 'TIDAK VALID: ada kontrol yang gagal. Jangan mengandalkan angka sampai diperbaiki.',
                'attention' => 'PERHATIAN: semua pembukuan seimbang, tetapi ada selisih subledger yang perlu dijelaskan.',
                default => 'VALID: seluruh kontrol konsisten.',
            },
            'controls' => $controls,
        ];
    }
}
