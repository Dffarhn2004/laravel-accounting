<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\FiscalPeriod;
use App\Models\Branch;
use App\Models\OpeningBalance;
use App\Models\TemplateOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The two ways to start the books for a branch (PRD 03-INPUTAN-TRANSAKSI.md):
 *  - Saldo Neraca (#/saldo): enter every balance-sheet account directly; must balance.
 *  - Setoran Modal Awal (#/modal): list assets and debts at cut-off; owner's capital
 *    (account 3102) is the balancing figure.
 *
 * They are mutually exclusive per branch + fiscal period and each posts exactly once.
 * Corrections go through reversal, never by posting a second opening.
 * Amounts use bcmath strings with 2 decimals; no floats touch the ledger.
 */
class OpeningPositionService
{
    public const SALDO_NERACA = 'saldo_neraca';

    public const MODAL_AWAL = 'modal_awal';

    private const METHOD_LABEL = [
        self::SALDO_NERACA => 'Saldo Neraca',
        self::MODAL_AWAL => 'Setoran Modal Awal',
    ];

    /** Journal side that increases the account's *position* on the balance sheet. */
    public static function sideFor(Account $account): string
    {
        $normalDebit = $account->type === 'asset';

        return ($normalDebit xor (bool) $account->is_contra) ? 'debit' : 'credit';
    }

    /**
     * Balance-sheet position of balances. Contra accounts subtract inside their own group.
     *
     * @param  array<int, string>  $amounts  account_id => positive amount
     * @param  \Illuminate\Support\Collection<int, Account>  $accounts  keyed by id
     * @return array{assets:string, liabilities:string, equity:string, difference:string}
     */
    public static function summarize($accounts, array $amounts): array
    {
        $totals = ['asset' => '0.00', 'liability' => '0.00', 'equity' => '0.00'];

        foreach ($amounts as $accountId => $amount) {
            $account = $accounts[$accountId] ?? null;
            if (! $account || ! isset($totals[$account->type])) {
                continue;
            }
            $totals[$account->type] = $account->is_contra
                ? bcsub($totals[$account->type], $amount, 2)
                : bcadd($totals[$account->type], $amount, 2);
        }

        return [
            'assets' => $totals['asset'],
            'liabilities' => $totals['liability'],
            'equity' => $totals['equity'],
            'difference' => bcsub($totals['asset'], bcadd($totals['liability'], $totals['equity'], 2), 2),
        ];
    }

    /** Fails when this branch + period already has any opening (either method, or the legacy page). */
    public static function ensureFree(int $companyId, int $branchId, int $periodId): void
    {
        $existing = OpeningBalance::where(['company_id' => $companyId, 'branch_id' => $branchId, 'fiscal_period_id' => $periodId])->first();

        if (! $existing) {
            return;
        }

        $used = self::METHOD_LABEL[$existing->method] ?? 'jurnal saldo awal';

        throw ValidationException::withMessages([
            'branch_id' => "Cabang ini sudah memiliki saldo awal ({$used}) untuk tahun buku ini. Saldo Neraca dan Setoran Modal Awal tidak boleh dipakai bersamaan, dan saldo awal hanya diposting sekali. Koreksi lewat jurnal pembalikan.",
        ]);
    }

    /**
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, posted_on:string, items:array<int, array{account_id:int, amount:string}>}  $data
     */
    public function postBalanceSheet(array $data): OpeningBalance
    {
        return DB::transaction(function () use ($data) {
            $this->lockBranch($data);
            self::ensureFree($data['company_id'], $data['branch_id'], $data['fiscal_period_id']);

            $items = $this->positive($data['items']);
            $accounts = Account::where('company_id', $data['company_id'])->where('is_active', true)
                ->whereIn('id', array_column($items, 'account_id'))->get()->keyBy('id');

            foreach ($items as $i => $item) {
                $account = $accounts[$item['account_id']] ?? null;
                if (! $account || ! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
                    throw ValidationException::withMessages(["items.{$i}.account_id" => 'Hanya akun neraca (aset, kewajiban, ekuitas) yang aktif yang boleh diisi.']);
                }
            }

            if (count(array_unique(array_column($items, 'account_id'))) !== count($items)) {
                throw ValidationException::withMessages(['items' => 'Satu akun hanya boleh muncul sekali di Saldo Neraca.']);
            }

            $summary = self::summarize($accounts, array_column($items, 'amount', 'account_id'));
            if (bccomp($summary['difference'], '0', 2) !== 0) {
                throw ValidationException::withMessages(['items' => "Neraca belum seimbang (selisih {$summary['difference']}). Aset harus sama dengan Kewajiban + Ekuitas."]);
            }

            $lines = array_map(fn ($item) => [
                'account_id' => $item['account_id'],
                'description' => null,
                'amount' => $item['amount'],
                'side' => self::sideFor($accounts[$item['account_id']]),
                'meta' => null,
            ], $items);

            return $this->post($data, self::SALDO_NERACA, 'Saldo Neraca Awal', $lines);
        });
    }

    /**
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, posted_on:string, items:array<int, array<string,mixed>>}  $data
     *   item: account_id, amount, description?, acquired_on?, useful_life_years?, accumulated?
     */
    public function postModalAwal(array $data): OpeningBalance
    {
        return DB::transaction(function () use ($data) {
            $this->lockBranch($data);
            self::ensureFree($data['company_id'], $data['branch_id'], $data['fiscal_period_id']);

            $items = $this->positive($data['items']);
            $accounts = Account::where('company_id', $data['company_id'])->where('is_active', true)
                ->whereIn('id', array_column($items, 'account_id'))->get()->keyBy('id');
            $allowed = TemplateOption::where(['company_id' => $data['company_id'], 'group' => 'opening_account', 'is_active' => true])->pluck('account_id')->filter()->all();
            $categories = AssetCategory::where('company_id', $data['company_id'])->get()->keyBy('asset_account_id');

            $lines = [];
            $net = []; // account_id => signed debit-minus-credit, for the journal

            foreach ($items as $i => $item) {
                $account = $accounts[$item['account_id']] ?? null;
                if (! $account || ! in_array($account->type, ['asset', 'liability'], true)) {
                    throw ValidationException::withMessages(["items.{$i}.account_id" => 'Modal awal hanya memuat harta dan hutang.']);
                }
                if ($allowed && ! in_array($account->id, $allowed, true)) {
                    throw ValidationException::withMessages(["items.{$i}.account_id" => 'Akun ini tidak diizinkan untuk modal awal (lihat Template Jurnal bagian 10).']);
                }

                $side = self::sideFor($account);
                $meta = null;
                $category = $categories[$account->id] ?? null;

                if ($category && $category->useful_life_years > 0) {
                    $acquired = $item['acquired_on'] ?? null;
                    if (! $acquired || $acquired > $data['posted_on']) {
                        throw ValidationException::withMessages(["items.{$i}.acquired_on" => 'Aset tetap wajib punya tanggal perolehan sebelum atau sama dengan tanggal cut-off.']);
                    }
                    $accumulated = $this->amount($item['accumulated'] ?? '0');
                    if (bccomp($accumulated, $item['amount'], 2) > 0) {
                        throw ValidationException::withMessages(["items.{$i}.accumulated" => 'Akumulasi penyusutan tidak boleh melebihi harga perolehan.']);
                    }
                    if (bccomp($accumulated, '0', 2) > 0) {
                        if (! $category->accumulated_account_id) {
                            throw ValidationException::withMessages(["items.{$i}.accumulated" => 'Kelompok aset belum punya akun akumulasi (Template Jurnal bagian 5).']);
                        }
                        $net[$category->accumulated_account_id] = bcsub($net[$category->accumulated_account_id] ?? '0.00', $accumulated, 2);
                    }
                    $meta = [
                        'acquired_on' => $acquired,
                        'useful_life_years' => (int) ($item['useful_life_years'] ?? $category->useful_life_years),
                        'accumulated' => $accumulated,
                    ];
                }

                $net[$account->id] = $side === 'debit'
                    ? bcadd($net[$account->id] ?? '0.00', $item['amount'], 2)
                    : bcsub($net[$account->id] ?? '0.00', $item['amount'], 2);

                $lines[] = [
                    'account_id' => $account->id,
                    'description' => $item['description'] ?? null,
                    'amount' => $item['amount'],
                    'side' => $side,
                    'meta' => $meta,
                ];
            }

            // Owner's capital = assets (net of accumulated depreciation) - liabilities.
            $capitalAccount = Account::where(['company_id' => $data['company_id'], 'code' => '3102'])->first();
            if (! $capitalAccount) {
                throw ValidationException::withMessages(['items' => 'Akun 3102 Modal Awal Pemilik belum ada. Muat template bawaan di Template Jurnal.']);
            }

            // Journal must balance: capital is the credit that offsets all debits minus credits of the items.
            $capital = '0.00';
            foreach ($net as $amount) {
                $capital = bcadd($capital, $amount, 2);
            }

            if (bccomp($capital, '0', 2) !== 0) {
                $lines[] = [
                    'account_id' => $capitalAccount->id,
                    'description' => 'Modal awal pemilik',
                    'amount' => ltrim($capital, '-'),
                    'side' => bccomp($capital, '0', 2) > 0 ? 'credit' : 'debit',
                    'meta' => null,
                ];
            }

            return $this->post($data, self::MODAL_AWAL, 'Setoran Modal Awal', $lines, $net, $capitalAccount->id, $capital);
        });
    }

    private function post(array $data, string $method, string $description, array $lines, array $net = [], ?int $capitalId = null, string $capital = '0.00'): OpeningBalance
    {
        $key = "opening-{$method}-{$data['branch_id']}-{$data['fiscal_period_id']}";

        $opening = OpeningBalance::create([
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'fiscal_period_id' => $data['fiscal_period_id'],
            'method' => $method,
            'posted_on' => $data['posted_on'],
            'description' => $description,
            'idempotency_key' => $key,
        ]);

        foreach ($lines as $line) {
            $opening->lines()->create($line);
        }

        // Journal lines: one per account, net of any contra effects already folded into $net.
        $journalLines = [];
        if ($method === self::MODAL_AWAL) {
            foreach ($net as $accountId => $amount) {
                if (bccomp($amount, '0', 2) === 0) {
                    continue;
                }
                $journalLines[] = bccomp($amount, '0', 2) > 0
                    ? ['account_id' => $accountId, 'debit' => $amount, 'credit' => '0']
                    : ['account_id' => $accountId, 'debit' => '0', 'credit' => ltrim($amount, '-')];
            }
            if ($capitalId && bccomp($capital, '0', 2) !== 0) {
                $journalLines[] = bccomp($capital, '0', 2) > 0
                    ? ['account_id' => $capitalId, 'debit' => '0', 'credit' => $capital]
                    : ['account_id' => $capitalId, 'debit' => ltrim($capital, '-'), 'credit' => '0'];
            }
        } else {
            foreach ($lines as $line) {
                $journalLines[] = $line['side'] === 'debit'
                    ? ['account_id' => $line['account_id'], 'debit' => $line['amount'], 'credit' => '0']
                    : ['account_id' => $line['account_id'], 'debit' => '0', 'credit' => $line['amount']];
            }
        }

        $entry = app(JournalPostingService::class)->post([
            'company_id' => $data['company_id'],
            'branch_id' => $data['branch_id'],
            'fiscal_period_id' => $data['fiscal_period_id'],
            'posted_on' => $data['posted_on'],
            'description' => $description,
            'idempotency_key' => $key,
            'source_type' => OpeningBalance::class,
            'source_id' => (string) $opening->id,
            'created_by' => $data['created_by'] ?? null,
            'lines' => $journalLines,
        ]);

        $opening->update(['journal_entry_id' => $entry->id]);

        return $opening->load('lines', 'journalEntry');
    }

    private function lockBranch(array $data): void
    {
        // Serialises concurrent submissions for the same branch.
        Branch::where('company_id', $data['company_id'])->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();

        $period = FiscalPeriod::where('company_id', $data['company_id'])->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['fiscal_period_id' => 'Tahun buku sudah ditutup.']);
        }
        if ($data['posted_on'] < $period->starts_on->toDateString() || $data['posted_on'] > $period->ends_on->toDateString()) {
            throw ValidationException::withMessages(['posted_on' => 'Tanggal harus berada dalam tahun buku yang dipilih.']);
        }
    }

    /** Drops blank/zero rows and validates and normalises amounts. */
    private function positive(array $items): array
    {
        $out = [];

        foreach (array_values($items) as $i => $item) {
            $amount = $this->amount($item['amount'] ?? '0');
            if (bccomp($amount, '0', 2) === 0) {
                continue;
            }
            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(["items.{$i}.amount" => 'Nilai harus lebih dari 0.']);
            }
            $out[] = ['amount' => $amount] + $item;
        }

        return $out;
    }

    private function amount(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '0.00';
        }
        if (! preg_match('/^\d{1,18}(\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['items' => 'Nilai uang tidak valid (angka positif maksimal 2 desimal).']);
        }

        return bcadd($value, '0', 2);
    }
}
