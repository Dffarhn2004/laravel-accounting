<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\TemplateOption;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Template Jurnal" (PRD docs/reference-catatrapi/02-SYSTEM.md): one menu, ten sections.
 * 1 Cabang, 2 Tahun, 3 Produk & harga, 4 Jenis persediaan, 5 Akun & umur aset,
 * 6-10 option lists (see ReferenceTemplateDefaults::GROUPS).
 */
class TemplateController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** Validation rule: account id must belong to the same company (no cross-tenant references). */
    private function accountRule(int $companyId, bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('accounts', 'id')->where('company_id', $companyId),
        ];
    }

    public function index(Request $request): Response
    {
        $companyId = $this->companyId($request);

        return Inertia::render('accounting/journal-template', [
            'groups' => ReferenceTemplateDefaults::GROUPS,
            'branches' => Branch::where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name', 'city', 'brand', 'is_active']),
            'years' => FiscalPeriod::where('company_id', $companyId)->orderByDesc('starts_on')->get(['id', 'starts_on', 'ends_on', 'status']),
            'products' => Product::where('company_id', $companyId)->orderBy('sku')->get(['id', 'sku', 'name', 'unit', 'price', 'revenue_account_id', 'is_active']),
            'inventoryTypes' => InventoryItem::where('company_id', $companyId)->orderBy('sku')->get(['id', 'sku', 'name', 'unit', 'minimum_stock', 'inventory_account_id', 'cogs_account_id', 'revenue_account_id', 'is_active']),
            'assetCategories' => AssetCategory::where('company_id', $companyId)->with('assetAccount:id,code,name')->get()->sortBy('assetAccount.code')->values(),
            'options' => TemplateOption::where('company_id', $companyId)->orderBy('group')->orderBy('sort_order')->orderBy('id')->get(['id', 'group', 'code', 'label', 'account_id', 'meta', 'is_active']),
            'accounts' => Account::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function loadDefaults(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        ReferenceTemplateDefaults::seed($company);

        return back()->with('success', 'Template bawaan dimuat. Isian yang sudah Anda ubah tidak ditimpa.');
    }

    // ---- 1. Cabang / kota / brand -------------------------------------------------

    public function storeBranch(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        Branch::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Cabang ditambahkan.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->company_id === $this->companyId($request), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Branches are never deleted (history), only deactivated; and not while users are assigned to it.
        if (array_key_exists('is_active', $data) && ! $data['is_active'] && User::where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['branch' => 'Cabang masih ditetapkan ke pengguna, tidak boleh dinonaktifkan.']);
        }

        $branch->update($data);

        return back()->with('success', 'Cabang diperbarui.');
    }

    // ---- 2. Template tahun --------------------------------------------------------

    public function storeYear(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);

        $exists = FiscalPeriod::where('company_id', $companyId)
            ->whereDate('starts_on', "{$data['year']}-01-01")
            ->whereDate('ends_on', "{$data['year']}-12-31")
            ->exists();

        if (! $exists) {
            FiscalPeriod::create([
                'company_id' => $companyId,
                'starts_on' => "{$data['year']}-01-01",
                'ends_on' => "{$data['year']}-12-31",
                'status' => 'open',
            ]);
        }

        return back()->with($exists ? 'info' : 'success', $exists ? 'Tahun buku sudah ada.' : 'Tahun buku dibuat.');
    }

    // ---- 3. Produk & harga --------------------------------------------------------

    public function storeProduct(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'revenue_account_id' => $this->accountRule($companyId),
        ]);
        $this->assertAccountType($data['revenue_account_id'], 'income', 'revenue_account_id');

        Product::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless($product->company_id === $companyId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'revenue_account_id' => $this->accountRule($companyId),
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->assertAccountType($data['revenue_account_id'], 'income', 'revenue_account_id');

        $product->update($data);

        return back()->with('success', 'Produk diperbarui.');
    }

    // ---- 4. Jenis persediaan ------------------------------------------------------

    public function storeInventoryType(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('inventory_items', 'sku')->where('company_id', $companyId)],
            ...$this->inventoryRules($companyId),
        ]);

        InventoryItem::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Jenis persediaan ditambahkan.');
    }

    public function updateInventoryType(Request $request, InventoryItem $item): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless($item->company_id === $companyId, 404);

        $item->update($request->validate($this->inventoryRules($companyId) + ['is_active' => ['sometimes', 'boolean']]));

        return back()->with('success', 'Jenis persediaan diperbarui.');
    }

    private function inventoryRules(int $companyId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'inventory_account_id' => $this->accountRule($companyId),
            'cogs_account_id' => $this->accountRule($companyId),
            'revenue_account_id' => $this->accountRule($companyId, false),
        ];
    }

    // ---- 5. Setting akun & umur aset ----------------------------------------------

    public function storeAssetCategory(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate([
            'asset_account_id' => [...$this->accountRule($companyId), Rule::unique('asset_categories', 'asset_account_id')->where('company_id', $companyId)],
            ...$this->assetRules($companyId),
        ]);
        $this->assertAssetCategory($data);

        AssetCategory::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Kelompok aset ditambahkan.');
    }

    public function updateAssetCategory(Request $request, AssetCategory $category): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless($category->company_id === $companyId, 404);

        $data = $request->validate($this->assetRules($companyId));
        $this->assertAssetCategory($data + ['asset_account_id' => $category->asset_account_id]);

        $category->update($data);

        return back()->with('success', 'Kelompok aset diperbarui.');
    }

    private function assetRules(int $companyId): array
    {
        return [
            'useful_life_years' => ['required', 'integer', 'between:0,80'],
            'accumulated_account_id' => $this->accountRule($companyId, false),
            'expense_account_id' => $this->accountRule($companyId, false),
        ];
    }

    /** Depreciable classes need both accounts; the accumulated account must be a contra asset. */
    private function assertAssetCategory(array $data): void
    {
        $this->assertAccountType($data['asset_account_id'], 'asset', 'asset_account_id');

        if ((int) $data['useful_life_years'] === 0) {
            return;
        }

        foreach (['accumulated_account_id', 'expense_account_id'] as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([$field => 'Wajib diisi untuk aset yang disusutkan (umur lebih dari 0).']);
            }
        }

        $this->assertAccountType($data['accumulated_account_id'], 'asset', 'accumulated_account_id');
        $this->assertAccountType($data['expense_account_id'], 'expense', 'expense_account_id');
    }

    // ---- 6-10. Option lists -------------------------------------------------------

    public function storeOption(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $group = $request->validate(['group' => ['required', Rule::in(array_keys(ReferenceTemplateDefaults::GROUPS))]])['group'];

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'account_id' => $this->accountRule($companyId, $group !== 'treasury_type'),
            'from_category' => [Rule::requiredIf($group === 'treasury_type'), 'nullable', 'string', 'max:80'],
            'to_category' => [Rule::requiredIf($group === 'treasury_type'), 'nullable', 'string', 'max:80'],
            'category' => [Rule::requiredIf($group === 'treasury_category'), 'nullable', 'string', 'max:80'],
        ]);

        $account = ! empty($data['account_id']) ? Account::find($data['account_id']) : null;
        $meta = match ($group) {
            'treasury_type' => ['from_category' => $data['from_category'], 'to_category' => $data['to_category']],
            'treasury_category' => ['category' => Str::slug($data['category'], '_')],
            default => null,
        };
        $code = match ($group) {
            'treasury_category' => $meta['category'].':'.$account->code,
            'opening_account' => $account->code,
            default => Str::slug($data['label'], '_'),
        };

        $exists = TemplateOption::where(['company_id' => $companyId, 'group' => $group, 'code' => $code])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['label' => 'Pilihan ini sudah ada.']);
        }

        TemplateOption::create([
            'company_id' => $companyId,
            'group' => $group,
            'code' => $code,
            'label' => $data['label'],
            'account_id' => $data['account_id'] ?? null,
            'meta' => $meta,
            'sort_order' => (int) TemplateOption::where(['company_id' => $companyId, 'group' => $group])->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Pilihan ditambahkan.');
    }

    public function updateOption(Request $request, TemplateOption $option): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless($option->company_id === $companyId, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'account_id' => $this->accountRule($companyId, $option->group !== 'treasury_type'),
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Category membership and the code are identity; only label, account and visibility change.
        if (in_array($option->group, ['treasury_category', 'opening_account'], true)) {
            unset($data['account_id']);
        }

        $option->update($data);

        return back()->with('success', 'Pilihan diperbarui.');
    }

    private function assertAccountType(int|string|null $accountId, string $type, string $field): void
    {
        if (Account::whereKey($accountId)->value('type') !== $type) {
            throw ValidationException::withMessages([$field => "Akun harus bertipe {$type}."]);
        }
    }
}
