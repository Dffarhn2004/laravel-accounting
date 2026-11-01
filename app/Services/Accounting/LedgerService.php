<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\ExpensePayment;
use App\Models\Receipt;
use App\Support\Accounting\JournalSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model over the journal: Jurnal Umum lines, per-account Buku Besar and the account summary.
 * The journal is the single source of truth; nothing here writes.
 *
 * Balances are shown on the account's normal side: assets and expenses are debit-normal,
 * liabilities, equity and income are credit-normal, and a contra account flips its group.
 * (SQL sums come back as floats on SQLite; they are rounded to cents before any bcmath use.
 * Production MySQL returns exact DECIMAL.)
 */
class LedgerService
{
    public const MONTHS = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function debitNormal(string $type, bool $contra): bool
    {
        return in_array($type, ['asset', 'expense'], true) xor $contra;
    }

    public static function money(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    /** Signed on the normal side: positive means the account sits on its normal side. */
    public static function normalSigned(string $net, string $type, bool $contra): string
    {
        return self::debitNormal($type, $contra) ? $net : bcmul($net, '-1', 2);
    }

    /**
     * Journal lines joined to their entry and account. Filters: branch_id, source, year, month,
     * from, to, account_id, search. Order: date, entry, line (oldest first).
     *
     * @param  array<string, mixed>  $filters
     */
    public function lines(int $companyId, array $filters = [], ?int $restrictToBranch = null): Builder
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $companyId)
            ->when($restrictToBranch, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->when($filters['source'] ?? null, fn ($q, $kind) => $kind === 'other' ? $q->whereNull('e.source_kind') : $q->where('e.source_kind', $kind))
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereYear('e.posted_on', $y))
            ->when($filters['month'] ?? null, fn ($q, $m) => $q->whereMonth('e.posted_on', $m))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('e.posted_on', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('e.posted_on', '<=', $d))
            ->when($filters['account_id'] ?? null, fn ($q, $id) => $q->where('l.account_id', $id))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('e.description', 'like', $like)->orWhere('e.reference', 'like', $like)
                    ->orWhere('l.description', 'like', $like)->orWhere('a.code', 'like', $like)->orWhere('a.name', 'like', $like));
            })
            ->orderBy('e.posted_on')->orderBy('e.id')->orderBy('l.id')
            ->select([
                'l.id as line_id', 'l.account_id', 'l.debit', 'l.credit', 'l.description as line_description',
                'e.id as entry_id', 'e.branch_id', 'e.posted_on', 'e.description as entry_description',
                'e.source_kind', 'e.source_id', 'e.reference',
                'a.code as account_code', 'a.name as account_name', 'a.type as account_type', 'a.is_contra',
            ]);
    }

    /** @return array{debit:string, credit:string, difference:string, lines:int} */
    public function totals(Builder $lines): array
    {
        $t = (clone $lines)->reorder()->select(DB::raw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c, COUNT(*) as n'))->first();
        $debit = self::money($t->d);
        $credit = self::money($t->c);

        return ['debit' => $debit, 'credit' => $credit, 'difference' => bcsub($debit, $credit, 2), 'lines' => (int) $t->n];
    }

    /**
     * Net (debit - credit) of each account strictly before the first displayed line of that
     * account, so a paginated page can carry a correct running balance.
     *
     * @param  Collection<int, object>  $pageRows  rows from lines(), in display order
     * @return array<int, string>  account_id => net before
     */
    public function netBefore(int $companyId, Collection $pageRows, ?int $branchId): array
    {
        $out = [];
        foreach ($pageRows->groupBy('account_id') as $accountId => $rows) {
            $first = $rows->first();
            // posted_on may come back as a datetime string; compare dates only so the line itself is never included.
            $day = \Illuminate\Support\Carbon::parse($first->posted_on)->toDateString();
            $net = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('e.company_id', $companyId)->where('l.account_id', $accountId)
                ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
                ->where(fn ($q) => $q->whereDate('e.posted_on', '<', $day)
                    ->orWhere(fn ($q2) => $q2->whereDate('e.posted_on', $day)->where(fn ($q3) => $q3->where('e.id', '<', $first->entry_id)
                        ->orWhere(fn ($q4) => $q4->where('e.id', $first->entry_id)->where('l.id', '<', $first->line_id)))))
                ->selectRaw('COALESCE(SUM(l.debit - l.credit),0) as net')->value('net');
            $out[(int) $accountId] = self::money($net);
        }

        return $out;
    }

    /**
     * Account summary for Buku Besar: opening balance before `from`, period debit/credit, closing.
     * Every active account of the company is listed, with zero when it has no postings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(int $companyId, ?string $from, ?string $to, ?int $branchId): array
    {
        $from ??= '0001-01-01';
        $aggregates = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.company_id', $companyId)
            ->when($branchId, fn ($q, $id) => $q->where('e.branch_id', $id))
            ->when($to, fn ($q, $d) => $q->whereDate('e.posted_on', '<=', $d))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id,
                COALESCE(SUM(CASE WHEN e.posted_on < ? THEN l.debit - l.credit ELSE 0 END),0) as opening,
                COALESCE(SUM(CASE WHEN e.posted_on >= ? THEN l.debit ELSE 0 END),0) as debit,
                COALESCE(SUM(CASE WHEN e.posted_on >= ? THEN l.credit ELSE 0 END),0) as credit', [$from, $from, $from])
            ->get()->keyBy('account_id');

        return Account::where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name', 'type', 'is_contra', 'is_active'])
            ->map(function (Account $a) use ($aggregates) {
                $row = $aggregates[$a->id] ?? null;
                $opening = self::money($row->opening ?? 0);
                $debit = self::money($row->debit ?? 0);
                $credit = self::money($row->credit ?? 0);
                $contra = (bool) $a->is_contra;
                $closingNet = bcadd($opening, bcsub($debit, $credit, 2), 2);

                return [
                    'account_id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'active' => (bool) $a->is_active,
                    'opening' => self::normalSigned($opening, $a->type, $contra),
                    'debit' => $debit, 'credit' => $credit,
                    'closing' => self::normalSigned($closingNet, $a->type, $contra),
                ];
            })->all();
    }

    /**
     * Counter accounts of each entry ("Akun Lawan"), e.g. "1111, 4101 +2".
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, array<int, string>>  entry_id => ordered list of "code" strings per line id
     */
    public function counterAccounts(Collection $rows): array
    {
        $entryIds = $rows->pluck('entry_id')->unique()->all();
        if (! $entryIds) {
            return [];
        }

        $all = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('l.journal_entry_id', $entryIds)->orderBy('l.id')->get(['l.id', 'l.journal_entry_id', 'l.account_id', 'a.code']);

        $out = [];
        foreach ($rows as $row) {
            $others = $all->where('journal_entry_id', $row->entry_id)->where('account_id', '!=', $row->account_id)->pluck('code')->unique()->values();
            $out[$row->line_id] = $others->count() > 3 ? $others->take(3)->implode(', ').' +'.($others->count() - 3) : $others->implode(', ');
        }

        return $out;
    }

    /**
     * Where the user can open the business document behind an entry (null when there is no page).
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, ?string>  line_id => url
     */
    public function sourceUrls(Collection $rows): array
    {
        $receipts = Receipt::whereIn('id', $rows->where('source_kind', 'receipt')->pluck('source_id')->all())->pluck('invoice_id', 'id');
        $payments = ExpensePayment::whereIn('id', $rows->where('source_kind', 'expense_payment')->pluck('source_id')->all())->pluck('expense_id', 'id');
        $out = [];

        foreach ($rows as $row) {
            $out[$row->line_id] = match ($row->source_kind) {
                'invoice' => "/transactions/invoices/{$row->source_id}",
                'expense' => "/transactions/expenses/{$row->source_id}",
                'receipt' => isset($receipts[$row->source_id]) ? "/transactions/invoices/{$receipts[$row->source_id]}" : null,
                'expense_payment' => isset($payments[$row->source_id]) ? "/transactions/expenses/{$payments[$row->source_id]}" : null,
                default => null,
            };
        }

        return $out;
    }

    public static function sourceLabel(?string $kind): string
    {
        return JournalSource::label($kind);
    }
}
