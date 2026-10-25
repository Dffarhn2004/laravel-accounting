<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_modules_require_authentication(): void
    {
        $this->get('/modules/laporan')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_from_implemented_modules(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/modules/master')->assertRedirect(route('accounting.master-data'));
        $this->get('/modules/transaksi')->assertRedirect(route('accounting.transactions'));
        $this->get('/modules/data')->assertRedirect(route('accounting.transactions'));
        $this->get('/modules/laporan')->assertRedirect(route('accounting.reports'));
        $this->get('/modules/anggaran')->assertRedirect(route('accounting.budgets'));
        $this->get('/modules/dividen')->assertOk();
    }

    public function test_unknown_module_is_not_exposed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/modules/nonexistent')->assertNotFound();
    }

    public function test_inertia_ssr_is_disabled_by_default_for_shared_hosting(): void
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
    }
}
