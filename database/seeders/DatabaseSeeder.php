<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(AccountingSeeder::class);

        $company = \App\Models\Company::where('code', 'DEMO')->firstOrFail();
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'company_id' => $company->id,
            'branch_id' => $company->branches()->value('id'),
        ]);
    }
}
