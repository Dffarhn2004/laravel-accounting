<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CashAccount;
use App\Support\Accounting\ReportStructure;

/**
 * Laporan Cashflow, indirect method (PRD 05-LAPORAN.md #5).
 *
 * Derived from the change in every balance-sheet account over the period:
 *     cash effect of an account = -(change in its debit - credit balance)
 *     Delta cash = net income + sum of all non-cash account effects
 * which holds exactly because every journal balances. Opening-balance journals dated inside the
 * period are the starting position, not cash flow.
 *
 * Buckets: depreciation add-back and working capital (operating), fixed assets (investing),
 * loans, equity and dividends (financing). A purchase of fixed assets on credit therefore shows
 * as investing outflow and a working-capital inflow (non-cash investing is not split out).
 * Cash = accounts of module "Kas & Bank" and accounts linked to a CashAccount.
 */
class CashFlowService
{
    public function __construct(private BalanceSheetService $balances) {}

    /** @return array<string, mixed> */
    public function report(int $companyId, string $from, string $to, ?int $branchId): array
    {
        $start = $this->balances->nets($companyId, $branchId, $to, $from, $to);
        $end = $this->balances->nets($companyId, $branchId, $to);

        $cashIds = CashAccount::where('company_id', $companyId)->pluck('account_id')->all();
        $accounts = Account::where('company_id', $companyId)->whereIn('type', ['asset', 'liability', 'equity'])->orderBy('code')->get(['id', 'code', 'name', 'type', 'module', 'is_contra']);

        $startCash = $endCash = '0.00';
        $groups = ['depreciation' => [], 'working_capital' => [], 'investing' => [], 'financing' => []];

        foreach ($accounts as $a) {
            $delta = bcsub($end[$a->id] ?? '0.00', $start[$a->id] ?? '0.00', 2);

            if ($a->module === 'Kas & Bank' || in_array($a->id, $cashIds, true)) {
                $startCash = bcadd($startCash, $start[$a->id] ?? '0.00', 2);
                $endCash = bcadd($endCash, $end[$a->id] ?? '0.00', 2);

                continue;
            }
            if (bccomp($delta, '0', 2) === 0) {
                continue;
            }

            $effect = bcmul($delta, '-1', 2);
            $groups[ReportStructure::cashFlowBucket($a->type, $a->module, (bool) $a->is_contra)][] = [
                'code' => $a->code, 'label' => $this->label($a, $effect), 'account_id' => $a->id, 'amount' => $effect,
            ];
        }

        $netIncome = $this->balances->netIncome($companyId, $branchId, $from, $to);
        $sum = fn (array $rows) => array_reduce($rows, fn ($c, $r) => bcadd($c, $r['amount'], 2), '0.00');

        $depreciation = $sum($groups['depreciation']);
        $workingCapital = $sum($groups['working_capital']);
        $operating = bcadd(bcadd($netIncome, $depreciation, 2), $workingCapital, 2);
        $investing = $sum($groups['investing']);
        $financing = $sum($groups['financing']);
        $increase = bcadd(bcadd($operating, $investing, 2), $financing, 2);
        $computedEnd = bcadd($startCash, $increase, 2);

        $rows = [];
        $heading = fn (string $label) => $rows[] = ['type' => 'heading', 'label' => $label];
        $detail = function (array $items) use (&$rows) {
            foreach ($items as $i) {
                $rows[] = ['type' => 'account', 'code' => $i['code'], 'label' => $i['label'], 'account_id' => $i['account_id'], 'values' => [$i['amount'], null]];
            }
        };
        $line = function (string $label, string $amount, string $type = 'account') use (&$rows) {
            $rows[] = ['type' => $type, 'label' => $label, 'code' => null, 'account_id' => null, 'values' => [null, $amount]];
        };

        $heading('Arus kas dari aktivitas operasi');
        $line('Laba (rugi) bersih', $netIncome);
        $rows[] = ['type' => 'section', 'label' => 'Penyesuaian beban non-kas'];
        $detail($groups['depreciation']);
        $line('Penyusutan', $depreciation, 'subtotal');
        $rows[] = ['type' => 'section', 'label' => 'Perubahan modal kerja'];
        $detail($groups['working_capital']);
        $line('Perubahan modal kerja', $workingCapital, 'subtotal');
        $line('Kas bersih dari aktivitas operasi', $operating, 'total');

        $heading('Arus kas dari aktivitas investasi');
        $detail($groups['investing']);
        $line('Kas bersih dari aktivitas investasi', $investing, 'total');

        $heading('Arus kas dari aktivitas pendanaan');
        $detail($groups['financing']);
        $line('Kas bersih dari aktivitas pendanaan', $financing, 'total');

        $line('Kenaikan (penurunan) bersih kas', $increase, 'total');
        $line('Kas dan setara kas awal periode', $startCash, 'account');
        $line('Kas dan setara kas akhir periode', $computedEnd, 'total');

        $heading('Rekonsiliasi');
        $line('Kas & Setara Kas pada Neraca', $endCash);
        $line('Selisih', bcsub($computedEnd, $endCash, 2), 'subtotal');

        return [
            'columns' => ['Rincian (Rp)', 'Jumlah (Rp)'],
            'rows' => $rows,
            'summary' => ['operating' => $operating, 'investing' => $investing, 'financing' => $financing, 'increase' => $increase, 'start_cash' => $startCash, 'end_cash' => $computedEnd, 'balance_sheet_cash' => $endCash, 'reconciled' => bccomp($computedEnd, $endCash, 2) === 0],
        ];
    }

    /** "Kenaikan Piutang Usaha" / "Penurunan Hutang Usaha" reads better than a bare account name. */
    private function label(Account $a, string $effect): string
    {
        if ($a->module === 'Aset Tetap' && ! $a->is_contra) {
            return ($effect[0] === '-' ? 'Perolehan ' : 'Pelepasan ').$a->name;
        }
        if ($a->module === 'Aset Tetap') {
            return 'Beban penyusutan '.str_replace('Akumulasi Penyusutan ', '', $a->name);
        }
        if (in_array($a->module, ['Pinjaman', 'Dividen', 'Ekuitas'], true)) {
            return $a->name;
        }

        $assetLike = $a->type === 'asset';
        $increase = $assetLike ? $effect[0] === '-' : $effect[0] !== '-';

        return ($increase ? 'Kenaikan ' : 'Penurunan ').$a->name;
    }
}
