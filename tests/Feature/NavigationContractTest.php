<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate A of docs/reference-catatrapi/09-PARITY-CHECKLIST.md:
 * 43 pages + Logout, identical label/order/grouping, one real destination each.
 */
class NavigationContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function contract(): array
    {
        $json = json_decode(
            file_get_contents(base_path('docs/reference-catatrapi/NAVIGATION-CONTRACT.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $json['navigation'];
    }

    /** @return array<int, string> flat, ordered list of "Group > Label" */
    private function flatten(array $sections, string $labelKey = 'label'): array
    {
        $flat = [];

        foreach ($sections as $section) {
            if ($section['type'] === 'group') {
                foreach ($section['items'] as $item) {
                    $flat[] = $section['label'].' > '.$item['id'].' > '.$item[$labelKey];
                }
            } else {
                $flat[] = $section['type'].' > '.$section['id'].' > '.$section['label'];
            }
        }

        return $flat;
    }

    public function test_sidebar_matches_the_reference_contract_exactly(): void
    {
        $this->assertSame(
            $this->flatten($this->contract()),
            $this->flatten(config('navigation.sections')),
        );
    }

    public function test_contract_has_43_pages_plus_logout(): void
    {
        $sections = config('navigation.sections');

        $pages = collect($sections)->sum(fn ($s) => $s['type'] === 'group' ? count($s['items']) : ($s['type'] === 'link' ? 1 : 0));

        $this->assertSame(43, $pages);
        $this->assertSame('action', end($sections)['type']);
        $this->assertSame('Logout', end($sections)['label']);
    }

    public function test_every_menu_has_one_unique_destination(): void
    {
        $paths = [];

        foreach (config('navigation.sections') as $section) {
            foreach ($section['type'] === 'group' ? $section['items'] : [$section] as $item) {
                if (! isset($item['path'])) {
                    continue;
                }
                $this->assertContains($item['status'], ['partial', 'pending'], $item['id']);
                $paths[] = $item['path'];
            }
        }

        $this->assertCount(43, $paths);
        $this->assertSame($paths, array_values(array_unique($paths)), 'Two menus share a destination');
    }

    public function test_every_menu_destination_resolves_for_authenticated_user(): void
    {
        // Real accounting pages require a company scope; a bare user is not a valid actor.
        $company = Company::create(['name' => 'Nav Co', 'code' => 'NAV']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $this->actingAs(User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]));

        foreach (config('navigation.sections') as $section) {
            foreach ($section['type'] === 'group' ? $section['items'] : [$section] as $item) {
                if (! isset($item['path'])) {
                    continue;
                }

                $response = $this->get($item['path']);
                $this->assertNotSame(404, $response->getStatusCode(), $item['id'].' => '.$item['path']);
                $this->assertLessThan(500, $response->getStatusCode(), $item['id'].' => '.$item['path']);
            }
        }
    }

    public function test_pending_pages_are_honest_and_require_authentication(): void
    {
        $this->get('/laporan/rasio')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get('/laporan/rasio')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pending')
                ->where('menu.id', 'rasio')
                ->where('group', 'Laporan'));
    }

    public function test_navigation_is_shared_with_the_frontend(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('navigation', 8)
            ->where('navigation.0.label', 'Dashboard')
            ->where('navigation.7.label', 'Logout'));
    }

    public function test_logout_is_a_real_auth_action(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }
}
