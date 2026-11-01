<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(['code' => 'DEMO'], ['name' => 'Demo Company']);
        $branch = $company->branches()->firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch']);

        ReferenceTemplateDefaults::seed($company); // also seeds the chart of accounts

        FiscalPeriod::firstOrCreate(
            ['company_id' => $company->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'],
            ['status' => 'open'],
        );
    }
}
