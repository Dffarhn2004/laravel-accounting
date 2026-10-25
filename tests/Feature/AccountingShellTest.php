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

    public function test_authenticated_user_can_open_all_prd_module_placeholders(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['master', 'transaksi', 'data', 'laporan', 'anggaran', 'dividen'] as $section) {
            $this->get("/modules/{$section}")->assertOk();
        }
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
