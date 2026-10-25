<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(['code' => 'DEMO'], ['name' => 'Demo Company']);
        $branch = $company->branches()->firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch']);

        foreach ([
            ['code' => '1100', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '3100', 'name' => 'Owner Equity', 'type' => 'equity'],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['code' => '5100', 'name' => 'Operating Expense', 'type' => 'expense'],
        ] as $account) {
            $company->accounts()->firstOrCreate(
                ['code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type']],
            );
        }

        FiscalPeriod::firstOrCreate(
            ['company_id' => $company->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'],
            ['status' => 'open'],
        );
    }
}
