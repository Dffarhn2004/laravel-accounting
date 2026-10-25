<?php

namespace App\Services\Accounting;

use App\Models\FixedAsset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FixedAssetReversalService
{
    public function reverseAcquisition(FixedAsset $asset, array $data): void
    {
        DB::transaction(function () use ($asset, $data): void {
            $asset->loadMissing('schedule');

            if ($asset->status === 'reversed') {
                throw new InvalidArgumentException('Asset acquisition has already been reversed.');
            }

            app(JournalReversalService::class)->reverse($asset->journalEntry, [
                'idempotency_key' => $data['idempotency_key'],
                'posted_on' => $data['posted_on'],
                'description' => 'Reversal: '.$asset->name,
            ]);

            $asset->update(['status' => 'reversed']);
        });
    }
}
