<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Accounting\Periods;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual journals (PRD 03 #10): 2 to 6 lines, exactly balanced, numbered JM-YYYY-00001.
 * The browser does not choose the fiscal period; the open year that contains the date does.
 */
class ManualJournalService
{
    /**
     * @param  array{company_id:int, branch_id:int, posted_on:string, description:string, idempotency_key:string, created_by?:int, lines:array<int, array{account_id:int, description?:string, debit:string, credit:string}>}  $data
     */
    public function record(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $companyId = (int) $data['company_id'];
            $existing = JournalEntry::where('company_id', $companyId)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }

            $postedOn = (string) $data['posted_on'];
            $period = Periods::openFor($companyId, $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal ini.');
            }

            $lines = $this->lines($companyId, $data['lines']);
            $number = $this->nextNumber($companyId, $postedOn);
            $entry = app(JournalPostingService::class)->post([
                'company_id' => $companyId,
                'branch_id' => (int) $data['branch_id'],
                'fiscal_period_id' => $period->id,
                'posted_on' => $postedOn,
                'description' => $data['description'],
                'idempotency_key' => $data['idempotency_key'],
                'source_type' => JournalEntry::class,
                'source_kind' => 'manual',
                'reference' => $number,
                'created_by' => $data['created_by'] ?? null,
                'lines' => $lines,
            ]);
            $entry->update(['source_id' => (string) $entry->id]);

            return $entry->fresh('lines');
        });
    }

    /**
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverse(JournalEntry $entry, array $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $entry = JournalEntry::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($entry->id);
            if ($entry->source_kind !== 'manual' || $entry->reversal_of_id) {
                throw new InvalidArgumentException('Jurnal ini tidak dibalik dari menu jurnal manual.');
            }
            if ($entry->status === 'reversed' || JournalEntry::where('reversal_of_id', $entry->id)->exists()) {
                throw new InvalidArgumentException('Jurnal ini sudah dibalik.');
            }

            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $entry->posted_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal jurnal.');
            }
            $period = Periods::openFor((int) $data['company_id'], $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }

            app(JournalReversalService::class)->reverse($entry, [
                'posted_on' => $postedOn,
                'fiscal_period_id' => $period->id,
                'description' => 'Pembalikan jurnal manual '.$entry->reference.': '.$data['reason'],
                'idempotency_key' => 'manual-reversal-'.$entry->id,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $entry->update(['status' => 'reversed']);

            return $entry->fresh();
        });
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array{account_id:int, debit:string, credit:string, description:?string}>
     */
    private function lines(int $companyId, array $rows): array
    {
        if (count($rows) < 2 || count($rows) > 6) {
            throw new InvalidArgumentException('Jurnal manual berisi 2 sampai 6 baris.');
        }

        $debit = '0.00';
        $credit = '0.00';
        $seen = [];
        $lines = [];
        foreach (array_values($rows) as $row) {
            $accountId = (int) $row['account_id'];
            if (isset($seen[$accountId])) {
                throw new InvalidArgumentException('Satu akun hanya boleh muncul sekali dalam jurnal manual.');
            }
            $seen[$accountId] = true;
            $account = Account::where('company_id', $companyId)->where('is_active', true)->find($accountId);
            if (! $account) {
                throw new InvalidArgumentException('Akun tidak aktif atau bukan milik perusahaan ini.');
            }
            $lineDebit = LedgerService::money($row['debit'] ?? '0');
            $lineCredit = LedgerService::money($row['credit'] ?? '0');
            $hasDebit = bccomp($lineDebit, '0', 2) > 0;
            $hasCredit = bccomp($lineCredit, '0', 2) > 0;
            if ($hasDebit === $hasCredit) {
                throw new InvalidArgumentException('Setiap baris diisi debit atau kredit, tidak keduanya dan tidak kosong.');
            }
            $debit = bcadd($debit, $lineDebit, 2);
            $credit = bcadd($credit, $lineCredit, 2);
            $lines[] = [
                'account_id' => $accountId,
                'debit' => $lineDebit,
                'credit' => $lineCredit,
                'description' => isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
            ];
        }
        if (bccomp($debit, $credit, 2) !== 0) {
            throw new InvalidArgumentException('Total debit harus sama dengan total kredit.');
        }

        return $lines;
    }

    private function nextNumber(int $companyId, string $postedOn): string
    {
        $prefix = 'JM-'.substr($postedOn, 0, 4).'-';
        $last = JournalEntry::where('company_id', $companyId)->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->lockForUpdate()->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }
}
