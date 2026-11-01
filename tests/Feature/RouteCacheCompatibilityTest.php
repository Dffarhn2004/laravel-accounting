<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteCacheCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_module_redirect_is_preserved_by_a_named_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/modules/transaksi')
            ->assertRedirect(route('accounting.transactions'));
    }

    public function test_passkey_well_known_endpoint_is_preserved_by_a_named_controller(): void
    {
        $this->get('/.well-known/passkey-endpoints')
            ->assertOk()
            ->assertJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    }
}
