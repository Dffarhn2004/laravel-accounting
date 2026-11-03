<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\DepreciationPosting;
use App\Models\DepreciationSchedule;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\OpeningBalance;
use App\Models\TreasuryTransaction;
use App\Support\Accounting\Periods;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Asset purchases and straight-line depreciation (ACC-08).
 * Depreciation starts the month after acquisition. For an opening-capital asset it starts
 * the month after the cutoff, and the cutoff accumulated balance is not reposted.
 */
class FixedAssetService
{
    public function nextReference(int $companyId, string $acquiredOn): string
    {
        return $this->reference($companyId, $acquiredOn, false);
    }

    /**
     * @param  array{company_id:int, branch_id:int, fiscal_period_id:int, acquired_on:string, cost:string, asset_account_id:int, settlement_account_id:int, idempotency_key:string, name?:string, description?:?string, asset_number?:string, code?:string, residual_value?:string, useful_life_months?:int, accumulated_depreciation_account_id?:?int, depreciation_expense_account_id?:?int, payment_method?:?string}  $data
     */
    public function acquire(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $existing = FixedAsset::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->fresh(['schedule', 'journalEntry']);
            }

            $this->scope($data);
            $data = $this->resolveCategory($data);
            $cost = $this->money($data['cost']);
            $residual = $this->money($data['residual_value'] ?? '0');
            if (bccomp($cost, '0', 2) <= 0 || bccomp($residual, '0', 2) < 0 || bccomp($residual, $cost, 2) > 0) {
                throw new InvalidArgumentException('Asset values are invalid.');
            }
            $life = (int) $data['useful_life_months'];
            if ($life > 0 && (empty($data['accumulated_depreciation_account_id']) || empty($data['depreciation_expense_account_id']))) {
                throw new InvalidArgumentException('Kelompok aset belum punya akun akumulasi dan beban penyusutan.');
            }

            $code = $data['asset_number'] ?? $data['code'] ?? $this->reference($data['company_id'], $data['acquired_on'], true);
            $asset = FixedAsset::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'code' => $code,
                'asset_number' => $data['asset_number'] ?? $code,
                'name' => $data['name'] ?? $data['description'] ?? 'Aset',
                'description' => $data['description'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'source' => 'purchase',
                'acquired_on' => $data['acquired_on'],
                'depreciation_from' => Carbon::parse($data['acquired_on'])->startOfMonth()->addMonth()->toDateString(),
                'cost' => $cost,
                'residual_value' => $residual,
                'useful_life_months' => $life,
                'asset_account_id' => $data['asset_account_id'],
                'settlement_account_id' => $data['settlement_account_id'],
                'accumulated_depreciation_account_id' => $data['accumulated_depreciation_account_id'] ?? null,
                'depreciation_expense_account_id' => $data['depreciation_expense_account_id'] ?? null,
                'status' => 'active',
                'idempotency_key' => $data['idempotency_key'],
            ]);
            DepreciationSchedule::create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'fixed_asset_id' => $asset->id,
                'period_month' => 1,
                'period_on' => $asset->acquired_on,
                'amount' => $this->monthlyAmount($asset),
                'monthly_amount' => $this->monthlyAmount($asset),
                'accumulated_amount' => '0.00',
                'book_value' => $asset->cost,
                'remaining_value' => $asset->cost,
            ]);
            $entry = app(JournalPostingService::class)->post([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'posted_on' => $data['acquired_on'],
                'description' => $data['description'] ?? 'Fixed asset acquisition',
                'idempotency_key' => $data['idempotency_key'],
                'source_type' => self::class,
                'source_kind' => 'asset',
                'reference' => $asset->code,
                'source_id' => (string) $asset->id,
                'created_by' => $data['created_by'] ?? null,
                'lines' => [
                    ['account_id' => $data['asset_account_id'], 'debit' => $cost, 'credit' => '0'],
                    ['account_id' => $data['settlement_account_id'], 'debit' => '0', 'credit' => $cost],
                ],
            ]);
            $asset->update(['journal_entry_id' => $entry->id]);

            return $asset->fresh(['schedule', 'journalEntry']);
        });
    }

    /** One register row per depreciable cutoff line. The opening journal is reused; nothing is posted again. */
    public function registerOpening(OpeningBalance $opening): void
    {
        $opening->loadMissing('lines.account');
        $capitalId = Account::where(['company_id' => $opening->company_id, 'code' => '3102'])->value('id');
        $categories = AssetCategory::where('company_id', $opening->company_id)->get()->keyBy('asset_account_id');

        foreach ($opening->lines as $line) {
            $meta = $line->meta;
            if (! is_array($meta) || empty($meta['acquired_on'])) {
                continue;
            }
            $category = $categories[$line->account_id] ?? null;
            $years = (int) ($meta['useful_life_years'] ?? $category?->useful_life_years ?? 0);
            if (! $category || $years <= 0) {
                continue;
            }
            if (! $category->accumulated_account_id || ! $category->expense_account_id || ! $capitalId) {
                throw ValidationException::withMessages(['items' => 'Kelompok aset belum lengkap (akun akumulasi, beban penyusutan, dan 3102).']);
            }
            $cost = $this->money($line->amount);
            $accumulated = $this->money($meta['accumulated'] ?? '0');
            $months = $years * 12;
            $monthly = bcdiv($cost, (string) $months, 2);
            $asset = FixedAsset::create([
                'company_id' => $opening->company_id,
                'branch_id' => $opening->branch_id,
                'fiscal_period_id' => $opening->fiscal_period_id,
                'code' => $code = $this->reference($opening->company_id, $opening->posted_on->toDateString(), true),
                'asset_number' => $code,
                'name' => $line->description ?: ($line->account?->name ?? 'Aset'),
                'description' => $line->description,
                'payment_method' => 'modal',
                'source' => 'opening',
                'acquired_on' => $meta['acquired_on'],
                'depreciation_from' => Carbon::parse($opening->posted_on)->startOfMonth()->addMonth()->toDateString(),
                'cost' => $cost,
                'residual_value' => '0.00',
                'useful_life_months' => $months,
                'asset_account_id' => $line->account_id,
                'settlement_account_id' => $capitalId,
                'accumulated_depreciation_account_id' => $category->accumulated_account_id,
                'depreciation_expense_account_id' => $category->expense_account_id,
                'journal_entry_id' => $opening->journal_entry_id,
                'opening_balance_id' => $opening->id,
                'status' => 'active',
                'idempotency_key' => 'opening-asset-'.$opening->id.'-'.$line->id,
            ]);
            DepreciationSchedule::create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'fixed_asset_id' => $asset->id,
                'period_month' => 1,
                'period_on' => $asset->acquired_on,
                'amount' => $monthly,
                'monthly_amount' => $monthly,
                'accumulated_amount' => $accumulated,
                'book_value' => bcsub($cost, $accumulated, 2),
                'remaining_value' => bcsub($cost, $accumulated, 2),
            ]);
        }
    }

    /** Refuse the deposit reversal while depreciation is still live; otherwise retire the register rows with it. */
    public function releaseOpening(OpeningBalance $opening): void
    {
        $assets = FixedAsset::where('opening_balance_id', $opening->id)->whereNull('reversed_at')->lockForUpdate()->get();
        foreach ($assets as $asset) {
            if ($asset->postings()->whereNull('reversed_at')->exists()) {
                throw new InvalidArgumentException('Aset modal awal sudah disusutkan. Balik penyusutannya di Data Asset terlebih dahulu.');
            }
        }
        foreach ($assets as $asset) {
            $asset->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_reason' => 'Setoran modal dibalik']);
        }
    }

    public function postDepreciation(FixedAsset $asset, string $month, string $idempotencyKey): DepreciationPosting
    {
        return DB::transaction(function () use ($asset, $month, $idempotencyKey) {
            $asset = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($asset->reversed_at || $asset->status === 'reversed') {
                throw new InvalidArgumentException('Aset sudah dibalik.');
            }
            if ($asset->useful_life_months <= 0 || ! $asset->depreciation_expense_account_id || ! $asset->accumulated_depreciation_account_id) {
                throw new InvalidArgumentException('Asset is not depreciable.');
            }
            $period = FiscalPeriod::where('company_id', $asset->company_id)
                ->where('starts_on', '<=', $month.'-01')
                ->where('ends_on', '>=', $month.'-01')->first();
            if (! $period) {
                throw new InvalidArgumentException('Depreciation period is not configured.');
            }
            if ($period->status !== 'open') {
                throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
            }
            if ($month < $this->firstMonth($asset)) {
                throw new InvalidArgumentException('Depreciation starts the month after acquisition.');
            }
            $this->assertSequence($asset, $month);
            if (DepreciationPosting::where('fixed_asset_id', $asset->id)->where('period_month', $month)->whereNull('reversed_at')->exists()) {
                throw new InvalidArgumentException('Depreciation month already posted.');
            }
            $schedule = $asset->schedule()->lockForUpdate()->firstOrFail();
            $amount = $this->dueAmount($asset, $schedule);
            if (bccomp($amount, '0', 2) <= 0) {
                throw new InvalidArgumentException('Asset is fully depreciated.');
            }
            $posting = DepreciationPosting::create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'fiscal_period_id' => $period->id,
                'fixed_asset_id' => $asset->id,
                'depreciation_schedule_id' => $schedule->id,
                'period_month' => $month,
                'posted_on' => $month.'-01',
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
            ]);
            $entry = app(JournalPostingService::class)->post([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'fiscal_period_id' => $period->id,
                'posted_on' => $month.'-01',
                'description' => 'Penyusutan '.$asset->code.' '.$month,
                'idempotency_key' => $idempotencyKey,
                'source_type' => self::class,
                'source_kind' => 'depreciation',
                'reference' => $asset->code,
                'source_id' => (string) $posting->id,
                'lines' => [
                    ['account_id' => $asset->depreciation_expense_account_id, 'debit' => $amount, 'credit' => '0'],
                    ['account_id' => $asset->accumulated_depreciation_account_id, 'debit' => '0', 'credit' => $amount],
                ],
            ]);
            $posting->update(['journal_entry_id' => $entry->id]);
            $schedule->update([
                'accumulated_amount' => bcadd((string) $schedule->accumulated_amount, $amount, 2),
                'book_value' => bcsub((string) $schedule->book_value, $amount, 2),
                'remaining_value' => bcsub((string) $schedule->book_value, $amount, 2),
            ]);

            return $posting->fresh('journalEntry');
        });
    }

    /** Post every missed month through $through (Y-m), oldest first. Already posted months are skipped. */
    public function postThrough(int $companyId, ?int $branchId, string $through): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $through) {
            $assets = FixedAsset::where('company_id', $companyId)
                ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
                ->where('status', 'active')->whereNull('reversed_at')
                ->where('useful_life_months', '>', 0)
                ->orderBy('id')->lockForUpdate()->get();
            $posted = 0;
            foreach ($assets as $asset) {
                $month = $this->firstMonth($asset);
                while ($month <= $through) {
                    $live = DepreciationPosting::where('fixed_asset_id', $asset->id)->where('period_month', $month)->whereNull('reversed_at')->exists();
                    if (! $live) {
                        $asset->load('schedule');
                        if (bccomp($this->dueAmount($asset, $asset->schedule), '0', 2) <= 0) {
                            break;
                        }
                        $this->postDepreciation($asset, $month, 'depreciation-'.$asset->id.'-'.$month);
                        $posted++;
                    }
                    $month = $this->nextMonth($month);
                }
            }

            return $posted;
        });
    }

    public function reverseDepreciation(FixedAsset $asset, array $data): DepreciationPosting
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = FixedAsset::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($asset->id);
            if ($asset->reversed_at) {
                throw new InvalidArgumentException('Aset sudah dibalik.');
            }
            $posting = DepreciationPosting::where('fixed_asset_id', $asset->id)->whereNull('reversed_at')->orderByDesc('period_month')->lockForUpdate()->first();
            if (! $posting) {
                throw new InvalidArgumentException('Tidak ada penyusutan yang dapat dibalik.');
            }
            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $posting->posted_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum bulan penyusutan.');
            }
            $period = Periods::openFor((int) $data['company_id'], $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }
            $entry = $posting->journalEntry()->firstOrFail();
            $reversal = app(JournalReversalService::class)->reverse($entry, [
                'posted_on' => $postedOn,
                'fiscal_period_id' => $period->id,
                'description' => 'Pembalikan penyusutan '.$asset->code.' '.$posting->period_month.': '.$data['reason'],
                'idempotency_key' => 'depreciation-reversal-'.$posting->id,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $entry->update(['idempotency_key' => $entry->idempotency_key.'#rev'.$posting->id]);
            $schedule = $asset->schedule()->lockForUpdate()->firstOrFail();
            $schedule->update([
                'accumulated_amount' => bcsub((string) $schedule->accumulated_amount, (string) $posting->amount, 2),
                'book_value' => bcadd((string) $schedule->book_value, (string) $posting->amount, 2),
            ]);
            $posting->update([
                'reversed_at' => now(),
                'reversal_journal_entry_id' => $reversal->id,
                'reversal_reason' => $data['reason'],
                'period_month' => $posting->period_month.'#'.$posting->id,
                'idempotency_key' => $posting->idempotency_key.'#rev'.$posting->id,
            ]);

            return $posting->fresh();
        });
    }

    public function reverseAcquisition(FixedAsset $asset, array $data): FixedAsset
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = FixedAsset::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($asset->id);
            if ($asset->source === 'opening') {
                throw new InvalidArgumentException('Aset dari modal awal dibalik lewat Data Modal Awal.');
            }
            if ($asset->reversed_at || $asset->status === 'reversed') {
                throw new InvalidArgumentException('Asset acquisition has already been reversed.');
            }
            if ($asset->postings()->whereNull('reversed_at')->exists()) {
                throw new InvalidArgumentException('Masih ada penyusutan hidup. Balik penyusutan dari bulan terakhir terlebih dahulu.');
            }
            $settlement = $asset->settlementAccount;
            if ($settlement && $settlement->type === 'liability' && TreasuryTransaction::query()
                ->where('company_id', $asset->company_id)
                ->where('branch_id', $asset->branch_id)
                ->where('transaction_type', 'bayar_hutang')
                ->where('to_account_id', $settlement->id)
                ->whereNull('reversed_at')
                ->whereDate('posted_on', '>=', $asset->acquired_on->toDateString())
                ->exists()) {
                throw new InvalidArgumentException('Masih ada pembayaran hutang yang lebih baru pada akun ini. Balik pembayaran itu terlebih dahulu.');
            }
            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $asset->acquired_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal perolehan.');
            }
            $period = Periods::openFor((int) $data['company_id'], $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }
            $reversal = app(JournalReversalService::class)->reverse($asset->journalEntry, [
                'idempotency_key' => $data['idempotency_key'] ?? ('asset-reversal-'.$asset->id),
                'posted_on' => $postedOn,
                'fiscal_period_id' => $period->id,
                'description' => 'Pembalikan pembelian aset '.$asset->code.': '.($data['reason'] ?? $asset->name),
                'created_by' => $data['created_by'] ?? null,
            ]);
            $asset->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversal_reason' => $data['reason'] ?? null,
                'reversal_journal_entry_id' => $reversal->id,
            ]);

            return $asset->fresh();
        });
    }

    public function firstMonth(FixedAsset $asset): string
    {
        if ($asset->depreciation_from) {
            return $asset->depreciation_from->format('Y-m');
        }

        return $asset->acquired_on->copy()->addMonth()->format('Y-m');
    }

    public function dueAmount(FixedAsset $asset, ?DepreciationSchedule $schedule = null): string
    {
        $schedule ??= $asset->schedule;
        if (! $schedule || $asset->useful_life_months <= 0) {
            return '0.00';
        }
        $remaining = bcsub($this->money(bcsub((string) $asset->cost, (string) $asset->residual_value, 2)), (string) $schedule->accumulated_amount, 2);
        if (bccomp($remaining, '0', 2) <= 0) {
            return '0.00';
        }
        $monthly = $this->money($schedule->amount);

        return bccomp($monthly, $remaining, 2) === 1 ? $remaining : $monthly;
    }

    /** @param  array<string, mixed>  $data */
    private function resolveCategory(array $data): array
    {
        if (array_key_exists('useful_life_months', $data)) {
            $data['residual_value'] = $data['residual_value'] ?? '0';

            return $data;
        }
        $category = AssetCategory::where('company_id', $data['company_id'])->where('asset_account_id', $data['asset_account_id'])->first();
        if (! $category) {
            throw new InvalidArgumentException('Akun aset belum punya kelompok di Template Jurnal bagian 5.');
        }
        $data['useful_life_months'] = (int) $category->useful_life_years * 12;
        $data['accumulated_depreciation_account_id'] = $category->accumulated_account_id;
        $data['depreciation_expense_account_id'] = $category->expense_account_id;
        $data['residual_value'] = '0';

        return $data;
    }

    private function assertSequence(FixedAsset $asset, string $month): void
    {
        $cursor = $this->firstMonth($asset);
        while ($cursor < $month) {
            $live = DepreciationPosting::where('fixed_asset_id', $asset->id)->where('period_month', $cursor)->whereNull('reversed_at')->exists();
            if (! $live) {
                throw new InvalidArgumentException('Bulan sebelumnya belum diposting.');
            }
            $cursor = $this->nextMonth($cursor);
        }
    }

    private function nextMonth(string $month): string
    {
        return Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m');
    }

    private function monthlyAmount(FixedAsset $asset): string
    {
        if ($asset->useful_life_months <= 0) {
            return '0.00';
        }

        return bcdiv(bcsub((string) $asset->cost, (string) $asset->residual_value, 2), (string) $asset->useful_life_months, 2);
    }

    private function reference(int $companyId, string $date, bool $lock): string
    {
        $prefix = 'AST-'.substr($date, 0, 4).'-';
        $query = FixedAsset::where('company_id', $companyId)->where('code', 'like', $prefix.'%')->orderByDesc('code');
        if ($lock) {
            $query->lockForUpdate();
        }
        $last = $query->value('code');

        return $prefix.str_pad((string) ($last ? ((int) substr((string) $last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    private function money(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    /** @param  array<string, mixed>  $data */
    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
        }
    }
}
