<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalReversalService
{
    public function reverse(JournalEntry $entry, array $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $entry->loadMissing('lines');

            if ($entry->reversal_of_id || JournalEntry::where('reversal_of_id', $entry->id)->exists()) {
                throw new InvalidArgumentException('Journal entry has already been reversed.');
            }

            $lines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => 'Reversal: '.$line->description,
            ])->all();

            $reversal = app(JournalPostingService::class)->post([
                'company_id' => $entry->company_id,
                'branch_id' => $entry->branch_id,
                'fiscal_period_id' => $data['fiscal_period_id'] ?? $entry->fiscal_period_id,
                'posted_on' => $data['posted_on'] ?? now()->toDateString(),
                'description' => $data['description'] ?? 'Reversal: '.$entry->description,
                'idempotency_key' => $data['idempotency_key'],
                'source_type' => 'reversal',
                'source_id' => (string) $entry->id,
                'created_by' => $data['created_by'] ?? null,
                'lines' => $lines,
            ]);

            $reversal->update(['reversal_of_id' => $entry->id]);

            return $reversal->load('lines');
        });
    }
}
