<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class JournalPostingService
{
    public function post(array $data): JournalEntry
    {
        $lines = $data['lines'] ?? [];
        $debit = '0.00';
        $credit = '0.00';

        foreach ($lines as $line) {
            $lineDebit = (string) ($line['debit'] ?? '0');
            $lineCredit = (string) ($line['credit'] ?? '0');
            if (bccomp($lineDebit, '0', 2) < 0 || bccomp($lineCredit, '0', 2) < 0 ||
                (bccomp($lineDebit, '0', 2) > 0 && bccomp($lineCredit, '0', 2) > 0) ||
                bccomp($lineDebit, '0', 2) === 0 && bccomp($lineCredit, '0', 2) === 0) {
                throw new InvalidArgumentException('Each line must contain exactly one positive debit or credit amount.');
            }
            $debit = bcadd($debit, $lineDebit, 2);
            $credit = bcadd($credit, $lineCredit, 2);
        }

        if (count($lines) < 2 || bccomp($debit, $credit, 2) !== 0) {
            throw new InvalidArgumentException('Journal entry must have at least two balanced lines.');
        }

        return DB::transaction(function () use ($data, $lines) {
            $period = FiscalPeriod::query()
                ->where('company_id', $data['company_id'])
                ->findOrFail($data['fiscal_period_id']);
            if ($period->status !== 'open') {
                throw new RuntimeException('Cannot post to a closed fiscal period.');
            }
            if ($data['posted_on'] < $period->starts_on->toDateString() ||
                $data['posted_on'] > $period->ends_on->toDateString()) {
                throw new InvalidArgumentException('Posting date is outside the fiscal period.');
            }
            Branch::query()->whereKey($data['branch_id'])
                ->where('company_id', $data['company_id'])->firstOrFail();
            $accountIds = collect($lines)->pluck('account_id')->unique();
            if ($accountIds->count() !== count($lines) ||
                Account::query()->where('company_id', $data['company_id'])
                    ->whereIn('id', $accountIds)->count() !== $accountIds->count()) {
                throw new InvalidArgumentException('All accounts must belong to the selected company.');
            }

            $existing = JournalEntry::query()
                ->where('company_id', $data['company_id'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'posted_on' => $data['posted_on'],
                'description' => $data['description'],
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'created_by' => $data['created_by'] ?? null,
            ]);
            $entry->lines()->createMany($lines);

            return $entry->load('lines');
        });
    }
}
