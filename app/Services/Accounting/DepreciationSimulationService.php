<?php

namespace App\Services\Accounting;

use App\Models\DepreciationPosting;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Simulasi & Output D&A (PRD 04 #6). Three tables; posting is a separate, ordered step. */
class DepreciationSimulationService
{
    public const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** @return array<string, mixed> */
    public function build(int $companyId, ?int $branchId, int $year, int $month): array
    {
        $through = sprintf('%04d-%02d', $year, $month);
        $asOf = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $from = sprintf('%04d-01-01', $year);
        $assets = FixedAsset::with(['schedule', 'assetAccount:id,code,name', 'expenseAccount:id,code,name', 'accumulatedAccount:id,code,name,type,is_contra'])
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('code')->get();
        $postings = DepreciationPosting::whereIn('fixed_asset_id', $assets->pluck('id'))->whereNull('reversed_at')->get()->groupBy('fixed_asset_id');

        $rows = [];
        $pending = [];
        $groups = [];
        foreach ($assets as $asset) {
            if ($asset->reversed_at) {
                continue;
            }
            $live = $postings->get($asset->id, collect());
            $postedSum = $live->reduce(fn ($c, $p) => bcadd($c, (string) $p->amount, 2), '0.00');
            $cutoff = bcsub((string) ($asset->schedule?->accumulated_amount ?? '0'), $postedSum, 2);
            $acquired = $asset->acquired_on->format('Y-m');
            $cells = [];
            for ($m = 1; $m <= 12; $m++) {
                $key = sprintf('%04d-%02d', $year, $m);
                if ($key < $acquired) {
                    $cells[] = null;

                    continue;
                }
                $added = $live->filter(fn ($p) => $p->period_month <= $key)->reduce(fn ($c, $p) => bcadd($c, (string) $p->amount, 2), '0.00');
                $accumulated = bcadd($cutoff, $added, 2);
                $cells[] = [
                    'cost' => (string) $asset->cost,
                    'accumulated' => $accumulated,
                    'book' => bcsub((string) $asset->cost, $accumulated, 2),
                ];
            }
            $rows[] = [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'account' => trim(($asset->assetAccount?->code ?? '').' '.($asset->assetAccount?->name ?? '')),
                'cells' => $cells,
            ];

            if ($asset->expenseAccount) {
                $id = $asset->expense_account_id;
                $groups[$id] ??= ['account' => $asset->expenseAccount->code.' — '.$asset->expenseAccount->name, 'months' => array_fill(0, 12, '0.00'), 'total' => '0.00'];
                foreach ($live as $posting) {
                    if (! str_starts_with($posting->period_month, (string) $year)) {
                        continue;
                    }
                    $index = (int) substr($posting->period_month, 5, 2) - 1;
                    if ($index < 0 || $index > 11) {
                        continue;
                    }
                    $groups[$id]['months'][$index] = bcadd($groups[$id]['months'][$index], (string) $posting->amount, 2);
                    $groups[$id]['total'] = bcadd($groups[$id]['total'], (string) $posting->amount, 2);
                }
            }

            if ($asset->useful_life_months <= 0) {
                continue;
            }
            $virtual = $cutoff;
            $cursor = app(FixedAssetService::class)->firstMonth($asset);
            while ($cursor <= $through) {
                $existing = $live->first(fn ($p) => $p->period_month === $cursor);
                if ($existing) {
                    $virtual = bcadd($virtual, (string) $existing->amount, 2);
                } else {
                    $depreciable = bcsub((string) $asset->cost, (string) $asset->residual_value, 2);
                    $remaining = bcsub($depreciable, $virtual, 2);
                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }
                    $monthly = bcadd((string) ($asset->schedule?->amount ?? '0'), '0', 2);
                    $amount = bccomp($monthly, $remaining, 2) === 1 ? $remaining : $monthly;
                    if (bccomp($amount, '0', 2) > 0) {
                        $pending[] = [
                            'code' => $asset->code,
                            'name' => $asset->name,
                            'month' => $cursor,
                            'amount' => $amount,
                            'expense' => $asset->expenseAccount ? $asset->expenseAccount->code.' — '.$asset->expenseAccount->name : null,
                            'accumulated' => $asset->accumulatedAccount ? $asset->accumulatedAccount->code.' — '.$asset->accumulatedAccount->name : null,
                        ];
                        $virtual = bcadd($virtual, $amount, 2);
                    }
                }
                $cursor = Carbon::createFromFormat('Y-m', $cursor)->addMonth()->format('Y-m');
            }
        }

        $expenseIds = $assets->pluck('depreciation_expense_account_id')->filter()->unique()->all();
        $expensePosted = '0.00';
        foreach ($groups as $group) {
            foreach ($group['months'] as $index => $amount) {
                if ($index < $month) {
                    $expensePosted = bcadd($expensePosted, $amount, 2);
                }
            }
        }
        $expenseLedger = $this->expenseLedger($companyId, $branchId, $expenseIds, $from, $asOf);
        $accumRegister = '0.00';
        $accumLedger = '0.00';
        $nets = app(BalanceSheetService::class)->nets($companyId, $branchId, $asOf);
        $seen = [];
        foreach ($assets as $asset) {
            if ($asset->reversed_at || ! $asset->accumulated_depreciation_account_id) {
                continue;
            }
            $added = ($postings->get($asset->id, collect()))->filter(fn ($p) => $p->period_month <= $through)->reduce(fn ($c, $p) => bcadd($c, (string) $p->amount, 2), '0.00');
            $postedAll = ($postings->get($asset->id, collect()))->reduce(fn ($c, $p) => bcadd($c, (string) $p->amount, 2), '0.00');
            $cutoff = bcsub((string) ($asset->schedule?->accumulated_amount ?? '0'), $postedAll, 2);
            $accumRegister = bcadd($accumRegister, bcadd($cutoff, $added, 2), 2);
            if (! isset($seen[$asset->accumulated_depreciation_account_id])) {
                $seen[$asset->accumulated_depreciation_account_id] = true;
                $account = $asset->accumulatedAccount;
                $accumLedger = bcadd($accumLedger, LedgerService::normalSigned($nets[$account->id] ?? '0.00', $account->type, (bool) $account->is_contra), 2);
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'months' => self::MONTHS,
            'assets' => $rows,
            'groups' => array_values($groups),
            'pending' => $pending,
            'reconciliation' => [
                'expense_posted' => $expensePosted,
                'expense_ledger' => $expenseLedger,
                'expense_difference' => bcsub($expenseLedger, $expensePosted, 2),
                'accumulated_register' => $accumRegister,
                'accumulated_ledger' => $accumLedger,
                'accumulated_difference' => bcsub($accumLedger, $accumRegister, 2),
            ],
        ];
    }

    /** @param  array<int, int>  $accountIds */
    private function expenseLedger(int $companyId, ?int $branchId, array $accountIds, string $from, string $to): string
    {
        if ($accountIds === []) {
            return '0.00';
        }
        $row = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)
            ->whereIn('l.account_id', $accountIds)
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->whereDate('e.posted_on', '>=', $from)
            ->whereDate('e.posted_on', '<=', $to)
            ->first([DB::raw('COALESCE(SUM(l.debit),0) as d'), DB::raw('COALESCE(SUM(l.credit),0) as c')]);

        return bcsub(LedgerService::money($row->d), LedgerService::money($row->c), 2);
    }
}
