<?php

namespace App\Support\Accounting;

use App\Models\FiscalPeriod;

final class Periods
{
    /** The open fiscal year that contains the date, or null when none is open. */
    public static function openFor(int $companyId, string $date): ?FiscalPeriod
    {
        return FiscalPeriod::where('company_id', $companyId)->where('status', 'open')
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
    }
}
