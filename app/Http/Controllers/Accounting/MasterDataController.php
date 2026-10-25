<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\InventoryItem;
use App\Models\CashAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MasterDataController extends Controller
{
    public function deactivateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->company_id === $request->user()->company_id, 404);
        $branch->update(['is_active' => false]);

        return back()->with('success', 'Branch deactivated.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        Branch::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Branch created.');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        Account::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Account created.');
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        Customer::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Customer created.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        Vendor::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Vendor created.');
    }

    public function storeInventoryItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'inventory_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'cogs_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'revenue_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        InventoryItem::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Inventory item created.');
    }

    public function storeCashAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', 'in:cash,bank,e-wallet'],
            'number' => ['nullable', 'string', 'max:100'],
        ]);
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        CashAccount::create($data + ['company_id' => $companyId]);

        return back()->with('success', 'Cash account created.');
    }

    public function deactivateAccount(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->company_id === $request->user()->company_id, 404);
        $account->update(['is_active' => false]);

        return back()->with('success', 'Account deactivated.');
    }

    public function index(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/master-data', [
            'company' => $companyId ? Company::find($companyId) : null,
            'branches' => Branch::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('name')->get(),
            'accounts' => Account::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('code')->get(),
            'customers' => Customer::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('name')->get(),
            'vendors' => Vendor::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('name')->get(),
            'taxCodes' => TaxCode::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('code')->get(),
            'inventoryItems' => InventoryItem::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('sku')->get(),
            'cashAccounts' => CashAccount::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->with('account')->orderBy('name')->get(),
        ]);
    }
}
