<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TemplateOption;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD docs/reference-catatrapi/02-SYSTEM.md "Template Jurnal" (10 sections in one menu).
 */
class JournalTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Tpl Co', 'code' => 'TPL']);
        $branch = $this->company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs($this->user);
    }

    private function account(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    public function test_defaults_cover_all_option_groups_and_are_idempotent(): void
    {
        $counts = $this->company->templateOptions()->selectRaw('`group`, count(*) as n')->groupBy('group')->pluck('n', 'group');

        foreach (array_keys(ReferenceTemplateDefaults::GROUPS) as $group) {
            $this->assertGreaterThan(0, $counts[$group] ?? 0, $group);
        }
        $this->assertSame(11, (int) $counts['treasury_type'], 'PRD: 11 kategori jenis transaksi kas/bank');
        $this->assertSame(6, $this->company->assetCategories()->count());

        $before = [$this->company->templateOptions()->count(), $this->company->assetCategories()->count()];
        ReferenceTemplateDefaults::seed($this->company);
        $this->assertSame($before, [$this->company->templateOptions()->count(), $this->company->assetCategories()->count()]);
    }

    public function test_every_treasury_type_points_at_categories_that_have_accounts(): void
    {
        $categories = TemplateOption::where('group', 'treasury_category')->get()->pluck('meta.category')->unique()->all();

        foreach (TemplateOption::where('group', 'treasury_type')->get() as $type) {
            $this->assertContains($type->meta['from_category'], $categories, $type->code);
            $this->assertContains($type->meta['to_category'], $categories, $type->code);
        }
    }

    public function test_default_asset_classes_are_wired_to_contra_and_expense_accounts(): void
    {
        $vehicle = $this->company->assetCategories()->where('asset_account_id', $this->account('1503'))->firstOrFail();

        $this->assertSame(8, $vehicle->useful_life_years);
        $this->assertSame($this->account('1553'), $vehicle->accumulated_account_id);
        $this->assertSame($this->account('5303'), $vehicle->expense_account_id);

        $land = $this->company->assetCategories()->where('asset_account_id', $this->account('1501'))->firstOrFail();
        $this->assertSame(0, $land->useful_life_years);
        $this->assertNull($land->accumulated_account_id);
    }

    public function test_page_renders_all_sections_and_requires_a_company(): void
    {
        $this->get('/system/template')->assertOk()->assertInertia(fn ($page) => $page
            ->component('accounting/journal-template')
            ->has('branches')->has('years')->has('products')->has('inventoryTypes')
            ->has('assetCategories', 6)->has('options')->has('accounts')->has('groups', 5));

        $this->actingAs(User::factory()->create());
        $this->get('/system/template')->assertForbidden();
    }

    public function test_load_defaults_endpoint_seeds_an_empty_company(): void
    {
        $fresh = Company::create(['name' => 'Fresh', 'code' => 'FRESH']);
        $this->actingAs(User::factory()->create(['company_id' => $fresh->id]));

        $this->post('/system/template/defaults')->assertRedirect();

        $this->assertGreaterThanOrEqual(70, $fresh->accounts()->count());
        $this->assertSame(11, $fresh->templateOptions()->where('group', 'treasury_type')->count());
    }

    public function test_branch_with_city_and_brand_unique_code_and_guarded_deactivation(): void
    {
        $this->post('/system/template/branches', ['code' => 'BDG', 'name' => 'Bandung', 'city' => 'Bandung', 'brand' => 'Kopi A'])->assertSessionHasNoErrors();
        $this->post('/system/template/branches', ['code' => 'BDG', 'name' => 'Dup'])->assertSessionHasErrors('code');

        $branch = $this->company->branches()->where('code', 'BDG')->firstOrFail();
        $this->patch("/system/template/branches/{$branch->id}", ['name' => 'Bandung 1', 'city' => 'Bandung', 'brand' => 'Kopi B', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('Kopi B', $branch->refresh()->brand);
        $this->assertFalse((bool) $branch->is_active);

        $assigned = $this->company->branches()->where('code', 'MAIN')->firstOrFail();
        $this->patch("/system/template/branches/{$assigned->id}", ['name' => 'Main', 'is_active' => false])->assertSessionHasErrors('branch');
        $this->assertTrue((bool) $assigned->refresh()->is_active);
    }

    public function test_fiscal_year_creation_is_idempotent(): void
    {
        $this->post('/system/template/years', ['year' => 2027])->assertSessionHasNoErrors();
        $this->post('/system/template/years', ['year' => 2027])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->company->fiscalPeriods()->whereDate('starts_on', '2027-01-01')->count());
        $this->post('/system/template/years', ['year' => 1800])->assertSessionHasErrors('year');
    }

    public function test_product_needs_an_income_account_of_the_same_company(): void
    {
        $this->post('/system/template/products', ['sku' => 'P1', 'name' => 'Kopi', 'unit' => 'cup', 'price' => '25000.50', 'revenue_account_id' => $this->account('4101')])->assertSessionHasNoErrors();
        $this->assertSame('25000.50', $this->company->products()->firstOrFail()->price);

        $this->post('/system/template/products', ['sku' => 'P2', 'name' => 'X', 'unit' => 'u', 'price' => '1', 'revenue_account_id' => $this->account('5101')])->assertSessionHasErrors('revenue_account_id');

        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $foreign = $other->accounts()->create(['code' => '4101', 'name' => 'Penjualan', 'type' => 'income']);
        $this->post('/system/template/products', ['sku' => 'P3', 'name' => 'X', 'unit' => 'u', 'price' => '1', 'revenue_account_id' => $foreign->id])->assertSessionHasErrors('revenue_account_id');

        $this->post('/system/template/products', ['sku' => 'P1', 'name' => 'Dup', 'unit' => 'u', 'price' => '1', 'revenue_account_id' => $this->account('4101')])->assertSessionHasErrors('sku');
        $this->post('/system/template/products', ['sku' => 'P4', 'name' => 'Neg', 'unit' => 'u', 'price' => '-1', 'revenue_account_id' => $this->account('4101')])->assertSessionHasErrors('price');
    }

    public function test_inventory_type_rejects_foreign_company_accounts_and_can_be_updated(): void
    {
        $payload = ['sku' => 'BJ', 'name' => 'Biji kopi', 'unit' => 'kg', 'minimum_stock' => '5', 'inventory_account_id' => $this->account('1301'), 'cogs_account_id' => $this->account('5101')];
        $this->post('/system/template/inventory', $payload)->assertSessionHasNoErrors();

        $item = $this->company->inventoryItems()->firstOrFail();
        $this->patch("/system/template/inventory/{$item->id}", ['name' => 'Biji kopi arabika'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('Biji kopi arabika', $item->refresh()->name);

        $foreign = Company::create(['name' => 'Other', 'code' => 'OTH'])->accounts()->create(['code' => '1301', 'name' => 'X', 'type' => 'asset']);
        $this->post('/system/template/inventory', ['sku' => 'ZZ', 'inventory_account_id' => $foreign->id] + $payload)->assertSessionHasErrors('inventory_account_id');
    }

    public function test_depreciable_asset_class_requires_accounts_of_the_right_type(): void
    {
        $tools = $this->account('1506');
        $category = $this->company->assetCategories()->where('asset_account_id', $tools)->firstOrFail();
        $accum = $this->account('1556');
        $expense = $this->account('5306');

        $this->patch("/system/template/assets/{$category->id}", ['useful_life_years' => 5, 'accumulated_account_id' => null, 'expense_account_id' => null])
            ->assertSessionHasErrors('accumulated_account_id');
        $this->patch("/system/template/assets/{$category->id}", ['useful_life_years' => 5, 'accumulated_account_id' => $accum, 'expense_account_id' => $accum])
            ->assertSessionHasErrors('expense_account_id');
        $this->patch("/system/template/assets/{$category->id}", ['useful_life_years' => 5, 'accumulated_account_id' => $accum, 'expense_account_id' => $expense])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $category->refresh()->useful_life_years);

        $this->patch("/system/template/assets/{$category->id}", ['useful_life_years' => 0, 'accumulated_account_id' => null, 'expense_account_id' => null])
            ->assertSessionHasNoErrors();
    }

    public function test_options_can_be_added_without_duplicates_and_identity_is_fixed(): void
    {
        $this->post('/system/template/options', ['group' => 'invoice_payment', 'label' => 'QRIS', 'account_id' => $this->account('1112')])->assertSessionHasNoErrors();
        $this->post('/system/template/options', ['group' => 'invoice_payment', 'label' => 'QRIS', 'account_id' => $this->account('1112')])->assertSessionHasErrors('label');
        $this->post('/system/template/options', ['group' => 'nope', 'label' => 'X', 'account_id' => $this->account('1112')])->assertSessionHasErrors('group');
        $this->post('/system/template/options', ['group' => 'treasury_type', 'label' => 'Setor ke investor'])->assertSessionHasErrors(['from_category', 'to_category']);

        $this->post('/system/template/options', ['group' => 'treasury_category', 'label' => 'Deposito', 'category' => 'Deposito', 'account_id' => $this->account('1203')])->assertSessionHasNoErrors();
        $this->assertTrue($this->company->templateOptions()->where('code', 'deposito:1203')->exists());

        $opening = $this->company->templateOptions()->where('group', 'opening_account')->where('code', '1101')->firstOrFail();
        $this->patch("/system/template/options/{$opening->id}", ['label' => 'Kas toko', 'account_id' => $this->account('2101'), 'is_active' => false])->assertSessionHasNoErrors();
        $opening->refresh();
        $this->assertSame('Kas toko', $opening->label);
        $this->assertSame($this->account('1101'), $opening->account_id, 'opening account identity must not change');
        $this->assertFalse((bool) $opening->is_active);
    }

    public function test_other_company_rows_cannot_be_modified(): void
    {
        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        ReferenceTemplateDefaults::seed($other);
        $branch = $other->branches()->create(['name' => 'B', 'code' => 'B']);
        $option = $other->templateOptions()->firstOrFail();

        $this->patch("/system/template/branches/{$branch->id}", ['name' => 'Hack'])->assertNotFound();
        $this->patch("/system/template/options/{$option->id}", ['label' => 'Hack', 'account_id' => $option->account_id])->assertNotFound();
    }
}
