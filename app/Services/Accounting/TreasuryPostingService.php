<?php

namespace App\Services\Accounting;

use App\Models\CashAccount;
use App\Models\OpeningBalance;
use App\Models\TreasuryTransaction;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TreasuryPostingService
{
    public function openingBalance(array $data): OpeningBalance
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            if (OpeningBalance::where('company_id', $data['company_id'])
                ->where('fiscal_period_id', $data['fiscal_period_id'])
                ->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Duplicate opening balance.');
            }

            $opening = OpeningBalance::create([
                ...$data,
                'status' => 'posted',
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Opening balance',
                'source_type' => self::class,
                'source_id' => (string) $opening->id,
                'lines' => $data['lines'],
            ]);

            return $opening;
        });
    }

    public function cashMovement(array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $cash = CashAccount::where('company_id', $data['company_id'])->findOrFail($data['cash_account_id']);
            $transaction = TreasuryTransaction::create([
                ...$data,
                'cash_account_id' => $cash->id,
                'status' => 'posted',
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Cash movement',
                'source_type' => self::class,
                'source_id' => (string) $transaction->id,
                'lines' => $data['lines'],
            ]);

            return $transaction->load('journalEntry.lines');
        });
    }

    public function bankTransfer(array $data): TreasuryTransaction
    {
        return $this->transfer($data);
    }

    public function transfer(array $data): TreasuryTransaction
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            if ($data['from_account_id'] === $data['to_account_id']) {
                throw new InvalidArgumentException('Transfer accounts must be different.');
            }

            $accounts = CashAccount::where('company_id', $data['company_id'])
                ->whereIn('id', [$data['from_cash_account_id'], $data['to_cash_account_id']])
                ->lockForUpdate()->get();
            if ($accounts->count() !== 2 || (string) $data['amount'] <= '0') {
                throw new InvalidArgumentException('Invalid cash transfer.');
            }

            $transaction = TreasuryTransaction::create([
                ...$data,
                'cash_account_id' => $data['from_cash_account_id'],
                'transaction_type' => 'transfer',
                'status' => 'posted',
                'description' => $data['description'] ?? 'Cash transfer',
            ]);
            app(JournalPostingService::class)->post([
                ...$data,
                'posted_on' => $data['posted_on'],
                'description' => $data['description'] ?? 'Cash transfer',
                'source_type' => self::class,
                'source_id' => (string) $transaction->id,
                'lines' => [
                    ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                    ['account_id' => $data['from_account_id'], 'debit' => '0', 'credit' => $data['amount']],
                ],
            ]);

            $transaction->update([
                'journal_entry_id' => JournalEntry::query()
                    ->where('company_id', $data['company_id'])
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->value('id'),
            ]);

            return $transaction->load('journalEntry.lines');
        });
    }

    private function scope(array $data): void
    {
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
        $period = FiscalPeriod::where('company_id', $data['company_id'])
            ->findOrFail($data['fiscal_period_id']);
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
        }
    }
}
