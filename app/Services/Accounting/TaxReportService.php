<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Pajak (PRD 05 #10), read-only over the journal.
 *
 * Per tax the obligation is the credit side of its liability account in a masa (month) and a
 * setoran is the debit side. Setoran are allocated to the oldest open masa first (FIFO); a
 * setoran with nothing open becomes an advance applied to the next masa that accrues.
 * A reversed entry and its reversal are left out as a pair, so a cancelled invoice never
 * counts as an obligation and a cancelled setoran never counts as paid.
 * Due dates are estimates only (ADR-008) and must be reviewed against current regulation.
 */
class TaxReportService
{
    /** key => [label, liability account code, credit account code (informational), due day of the following month, due label] */
    public const TAXES = [
        'ppn' => ['label' => 'PPN', 'liability' => '2301', 'credit' => '1403', 'credit_label' => 'PPN Masukan', 'due_day' => 'end', 'due_label' => 'akhir bulan berikutnya'],
        'pph21' => ['label' => 'PPh 21', 'liability' => '2302', 'credit' => null, 'credit_label' => null, 'due_day' => 10, 'due_label' => 'tanggal 10 bulan berikutnya'],
        'pph23' => ['label' => 'PPh 23', 'liability' => '2303', 'credit' => '1404', 'credit_label' => 'PPh 23 dibayar di muka', 'due_day' => 10, 'due_label' => 'tanggal 10 bulan berikutnya'],
        'pph_badan' => ['label' => 'PPh Badan', 'liability' => '2304', 'credit' => null, 'credit_label' => null, 'due_day' => 15, 'due_label' => 'tanggal 15 bulan berikutnya'],
    ];

    public const EXPENSE_PREFIX = '55';

    public const LEDGER_LIMIT = 500;

    public const STATUS_LABELS = ['none' => 'Tidak ada', 'paid' => 'Lunas', 'partial' => 'Sebagian', 'unpaid' => 'Belum disetor'];

    /**
     * @param  array{company_id:int, year?:?int, branch_id?:?int, tax?:?string, month?:?int, status?:?string, today?:?string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $companyId = (int) $filters['company_id'];
        $year = (int) ($filters['year'] ?? now()->year);
        $branchId = $filters['branch_id'] ?? null;
        $taxFilter = $filters['tax'] ?? 'all';
        $month = $filters['month'] ?? null;
        $status = $filters['status'] ?? 'all';
        $today = Carbon::parse($filters['today'] ?? now()->toDateString())->startOfDay();
        $yearEnd = sprintf('%04d-12-31', $year);

        $taxKeys = $taxFilter === 'all' ? array_keys(self::TAXES) : [$taxFilter];
        $accounts = Account::where('company_id', $companyId)->get(['id', 'code', 'name'])->keyBy('code');

        $summary = [];
        $masaRows = [];
        $reconciliation = [];
        foreach ($taxKeys as $key) {
            $def = self::TAXES[$key];
            $liability = $accounts[$def['liability']] ?? null;
            $credit = $def['credit'] ? ($accounts[$def['credit']] ?? null) : null;
            $monthly = $liability ? $this->monthly($companyId, $branchId, (int) $liability->id, $yearEnd) : [];
            $creditMonthly = $credit ? $this->monthly($companyId, $branchId, (int) $credit->id, $yearEnd) : [];
            $masas = $this->allocate($monthly);

            $carry = '0.00';
            $accrued = '0.00';
            $paid = '0.00';
            $remaining = '0.00';
            foreach ($masas as $period => $m) {
                $inYear = str_starts_with($period, $year.'-');
                if (! $inYear) {
                    $carry = bcadd($carry, $m['remaining'], 2);

                    continue;
                }
                $due = $this->dueDate($period, $def['due_day']);
                $overdue = bccomp($m['remaining'], '0', 2) > 0 && $today->gt($due);
                $row = [
                    'tax' => $key, 'tax_label' => $def['label'], 'period' => $period,
                    'masa' => LedgerService::MONTHS[(int) substr($period, 5, 2)].' '.substr($period, 0, 4),
                    'accrued' => $m['accrued'], 'paid' => $m['paid'], 'remaining' => $m['remaining'],
                    'credit' => LedgerService::money($creditMonthly[$period]['debit'] ?? 0),
                    'status' => $m['status'], 'status_label' => self::STATUS_LABELS[$m['status']],
                    'overdue' => $overdue, 'due_estimate' => $due->toDateString(),
                    'url' => '/laporan/lap-pajak?'.http_build_query(array_filter(['tax' => $key, 'year' => $year, 'month' => (int) substr($period, 5, 2), 'branch_id' => $branchId])),
                ];
                $accrued = bcadd($accrued, $m['accrued'], 2);
                $paid = bcadd($paid, $m['paid'], 2);
                $remaining = bcadd($remaining, $m['remaining'], 2);
                $masaRows[] = $row;
            }

            $outstandingAll = bcadd($carry, $remaining, 2);
            $excess = $masas ? (string) end($masas)['credit_pool'] : '0.00';
            $ledger = $liability ? $this->ledgerBalance($companyId, $branchId, (int) $liability->id, $yearEnd) : '0.00';
            $summary[] = [
                'tax' => $key, 'tax_label' => $def['label'], 'account' => $liability ? $liability->code.' — '.$liability->name : $def['liability'].' (akun belum ada)',
                'carry_forward' => $carry, 'accrued' => $accrued, 'paid' => $paid, 'remaining' => $remaining,
                'outstanding' => $outstandingAll, 'advance' => $excess,
                'credit_label' => $def['credit_label'],
                'credit_balance' => $credit ? $this->debitBalance($companyId, $branchId, (int) $credit->id, $yearEnd) : null,
                'due_rule' => $def['due_label'],
            ];
            $reconciliation[] = [
                'tax' => $key, 'tax_label' => $def['label'],
                'report' => bcsub($outstandingAll, $excess, 2),
                'ledger' => $ledger,
                'difference' => bcsub(bcsub($outstandingAll, $excess, 2), $ledger, 2),
            ];
        }

        $masaRows = array_values(array_filter($masaRows, function (array $r) use ($month, $status) {
            if ($month && (int) substr($r['period'], 5, 2) !== (int) $month) {
                return false;
            }

            return match ($status) {
                'open' => in_array($r['status'], ['unpaid', 'partial'], true),
                'paid' => $r['status'] === 'paid',
                'overdue' => $r['overdue'],
                default => true,
            };
        }));
        usort($masaRows, fn ($a, $b) => [$a['period'], $a['tax']] <=> [$b['period'], $b['tax']]);

        return [
            'summary' => $summary,
            'masa' => $masaRows,
            'ledger' => $this->subLedger($companyId, $branchId, $taxKeys, $accounts->all(), $year, $month),
            'expense' => $this->expense($companyId, $branchId, $year, $month),
            'reconciliation' => $reconciliation,
        ];
    }

    /**
     * Liability account movement per month, reversal pairs excluded, from the first posting to year end.
     *
     * @return array<string, array{debit:string, credit:string}> 'YYYY-MM' => totals, ascending
     */
    private function monthly(int $companyId, ?int $branchId, int $accountId, string $to): array
    {
        $rows = $this->pairFreeLines($companyId, $branchId, $accountId)->whereDate('e.posted_on', '<=', $to)->get(['e.posted_on', 'l.debit', 'l.credit']);
        $out = [];
        foreach ($rows as $r) {
            $p = substr((string) $r->posted_on, 0, 7);
            $out[$p] ??= ['debit' => '0.00', 'credit' => '0.00'];
            $out[$p]['debit'] = bcadd($out[$p]['debit'], LedgerService::money($r->debit), 2);
            $out[$p]['credit'] = bcadd($out[$p]['credit'], LedgerService::money($r->credit), 2);
        }
        ksort($out);

        return $out;
    }

    /** Journal lines of an account without entries that were reversed or are reversals. */
    private function pairFreeLines(int $companyId, ?int $branchId, int $accountId)
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)->where('l.account_id', $accountId)
            ->whereNull('e.reversal_of_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries as r')->whereColumn('r.reversal_of_id', 'e.id'))
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id));
    }

    /**
     * FIFO allocation of setoran to the oldest masa; leftover setoran waits as an advance.
     *
     * @param  array<string, array{debit:string, credit:string}>  $monthly
     * @return array<string, array{accrued:string, paid:string, remaining:string, status:string, credit_pool:string}>
     */
    private function allocate(array $monthly): array
    {
        $out = [];
        $open = []; // period => remaining, oldest first
        $pool = '0.00';
        foreach ($monthly as $period => $m) {
            $out[$period] = ['accrued' => $m['credit'], 'paid' => '0.00', 'remaining' => $m['credit'], 'status' => 'none', 'credit_pool' => '0.00'];
            $open[$period] = $m['credit'];
            $pool = bcadd($pool, $m['debit'], 2);
            foreach ($open as $p => $rest) {
                if (bccomp($pool, '0', 2) <= 0) {
                    break;
                }
                $apply = bccomp($pool, $rest, 2) >= 0 ? $rest : $pool;
                $open[$p] = bcsub($rest, $apply, 2);
                $out[$p]['paid'] = bcadd($out[$p]['paid'], $apply, 2);
                $pool = bcsub($pool, $apply, 2);
            }
            $out[$period]['credit_pool'] = $pool;
            foreach ($open as $p => $rest) {
                if (bccomp($rest, '0', 2) === 0) {
                    unset($open[$p]);
                }
            }
        }
        foreach ($out as $p => &$m) {
            $m['remaining'] = bcsub($m['accrued'], $m['paid'], 2);
            $m['status'] = bccomp($m['accrued'], '0', 2) === 0 ? 'none'
                : (bccomp($m['remaining'], '0', 2) === 0 ? 'paid' : (bccomp($m['paid'], '0', 2) > 0 ? 'partial' : 'unpaid'));
        }
        unset($m);

        return $out;
    }

    /** Estimated due date: a fixed day (or month end) of the month after the masa. Estimate only. */
    private function dueDate(string $period, int|string $day): Carbon
    {
        $next = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay()->addMonthNoOverflow();

        return $day === 'end' ? $next->endOfMonth()->startOfDay() : $next->day((int) $day);
    }

    private function ledgerBalance(int $companyId, ?int $branchId, int $accountId, string $to): string
    {
        $t = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)->where('l.account_id', $accountId)->whereDate('e.posted_on', '<=', $to)
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')->first();

        return bcsub(LedgerService::money($t->c), LedgerService::money($t->d), 2);
    }

    private function debitBalance(int $companyId, ?int $branchId, int $accountId, string $to): string
    {
        return bcmul($this->ledgerBalance($companyId, $branchId, $accountId, $to), '-1', 2);
    }

    /**
     * Buku pembantu: every journal line on the tax accounts of the chosen taxes within the year.
     *
     * @param  array<int, string>  $taxKeys
     * @param  array<string, Account>  $accounts
     * @return array{rows:array<int, array<string, mixed>>, truncated:bool}
     */
    private function subLedger(int $companyId, ?int $branchId, array $taxKeys, array $accounts, int $year, ?int $month): array
    {
        $codes = [];
        foreach ($taxKeys as $key) {
            $codes[] = self::TAXES[$key]['liability'];
            if (self::TAXES[$key]['credit']) {
                $codes[] = self::TAXES[$key]['credit'];
            }
        }
        $ids = collect($codes)->map(fn ($c) => $accounts[$c]->id ?? null)->filter()->values()->all();
        if (! $ids) {
            return ['rows' => [], 'truncated' => false];
        }

        $q = (new LedgerService)->lines($companyId, ['branch_id' => $branchId, 'year' => $year, 'month' => $month])->whereIn('l.account_id', $ids);
        $rows = (clone $q)->limit(self::LEDGER_LIMIT + 1)->get();
        $truncated = $rows->count() > self::LEDGER_LIMIT;
        $rows = $rows->take(self::LEDGER_LIMIT);
        $urls = (new LedgerService)->sourceUrls($rows);

        return [
            'truncated' => $truncated,
            'rows' => $rows->map(fn ($r) => [
                'date' => substr((string) $r->posted_on, 0, 10),
                'reference' => $r->reference,
                'description' => $r->line_description ?: $r->entry_description,
                'account' => $r->account_code.' — '.$r->account_name,
                'debit' => LedgerService::money($r->debit),
                'credit' => LedgerService::money($r->credit),
                'source' => LedgerService::sourceLabel($r->source_kind),
                'url' => $urls[$r->line_id] ?? null,
            ])->all(),
        ];
    }

    /**
     * Blok D: beban pajak (akun 55xx) in Laba Rugi, one row per account, with the twelve months.
     *
     * @return array{rows:array<int, array<string, mixed>>, total:string}
     */
    private function expense(int $companyId, ?int $branchId, int $year, ?int $month): array
    {
        $rows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)->where('a.type', 'expense')->where('a.code', 'like', self::EXPENSE_PREFIX.'%')
            ->whereYear('e.posted_on', $year)
            ->when($month, fn ($q, $m) => $q->whereMonth('e.posted_on', $m))
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->get(['a.code', 'a.name', 'e.posted_on', 'l.debit', 'l.credit']);

        $byAccount = [];
        foreach ($rows as $r) {
            $key = $r->code;
            $byAccount[$key] ??= ['code' => $r->code, 'name' => $r->name, 'months' => array_fill(1, 12, '0.00'), 'total' => '0.00'];
            $m = (int) substr((string) $r->posted_on, 5, 2);
            $net = bcsub(LedgerService::money($r->debit), LedgerService::money($r->credit), 2);
            $byAccount[$key]['months'][$m] = bcadd($byAccount[$key]['months'][$m], $net, 2);
            $byAccount[$key]['total'] = bcadd($byAccount[$key]['total'], $net, 2);
        }
        ksort($byAccount);
        $total = '0.00';
        foreach ($byAccount as $a) {
            $total = bcadd($total, $a['total'], 2);
        }

        return ['rows' => array_values($byAccount), 'total' => $total];
    }

    public const COLUMNS = ['Pajak', 'Masa', 'Terutang', 'Disetor', 'Sisa', 'Status', 'Jatuh Tempo (perkiraan)', 'Kredit Pajak'];

    /** @param  array<string, mixed>  $row */
    public static function csvRow(array $row): array
    {
        return [
            $row['tax_label'], $row['masa'], $row['accrued'], $row['paid'], $row['remaining'],
            $row['status_label'].($row['overdue'] ? ' (lewat perkiraan jatuh tempo)' : ''), $row['due_estimate'], $row['credit'],
        ];
    }
}
