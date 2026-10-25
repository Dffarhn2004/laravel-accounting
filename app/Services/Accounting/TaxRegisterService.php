<?php

namespace App\Services\Accounting;

use App\Models\TaxTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxRegisterService
{
    public function record(array $data): TaxTransaction
    {
        return DB::transaction(function () use ($data) {
            if (TaxTransaction::where('company_id', $data['company_id'])
                ->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Duplicate tax register entry.');
            }

            return TaxTransaction::create($data);
        });
    }
}
