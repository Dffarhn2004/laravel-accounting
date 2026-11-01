<?php

namespace Tests\Feature\Settings;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_business_identity_bank_account_and_default_tax_rates(): void
    {
        $company = Company::create(['name' => 'Usaha Lama', 'code' => 'LAMA']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->patch(route('company.update'), [
                'name' => 'Usaha Baru',
                'code' => 'BARU',
                'address' => 'Jl. Merdeka 10, Jakarta',
                'phone' => '021-123456',
                'email' => 'admin@usahabaru.test',
                'invoice_bank_name' => 'Bank Contoh',
                'invoice_bank_account_name' => 'Usaha Baru',
                'invoice_bank_account_number' => '1234567890',
                'default_vat_rate' => '11.00',
                'default_pph23_rate' => '2.00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('company.edit'));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Usaha Baru',
            'code' => 'BARU',
            'address' => 'Jl. Merdeka 10, Jakarta',
            'phone' => '021-123456',
            'email' => 'admin@usahabaru.test',
            'invoice_bank_name' => 'Bank Contoh',
            'invoice_bank_account_name' => 'Usaha Baru',
            'invoice_bank_account_number' => '1234567890',
            'default_vat_rate' => '11.00',
            'default_pph23_rate' => '2.00',
        ]);
    }

    public function test_company_tax_rates_must_be_decimal_percentages_between_zero_and_one_hundred(): void
    {
        $company = Company::create(['name' => 'Usaha', 'code' => 'USAHA']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->from(route('company.edit'))
            ->patch(route('company.update'), [
                'name' => 'Usaha', 'code' => 'USAHA',
                'default_vat_rate' => '100.001',
                'default_pph23_rate' => '-1',
            ])
            ->assertRedirect(route('company.edit'))
            ->assertSessionHasErrors(['default_vat_rate', 'default_pph23_rate']);
    }

    public function test_user_without_a_company_cannot_update_company_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('company.update'), [])
            ->assertForbidden();
    }
}
