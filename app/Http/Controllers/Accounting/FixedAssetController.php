<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedAssetController extends Controller
{
    public function index(Request $request)
    {
        $assets = FixedAsset::query()
            ->with('schedule')
            ->where('company_id', $request->user()?->company_id)
            ->when($request->user()?->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->latest('acquired_on')
            ->paginate(25)
            ->withQueryString();

        $companyId = $request->user()?->company_id;
        return Inertia::render('accounting/fixed-assets', [
            'assets' => $assets,
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
        ]);
    }

    public function store(Request $request, FixedAssetService $service): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'acquired_on' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'residual_value' => ['required', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'asset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'settlement_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'depreciation_expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:150'],
        ]);
        $service->acquire($data + ['company_id' => $request->user()->company_id]);

        return back()->with('success', 'Fixed asset acquired.');
    }

    public function depreciate(Request $request, FixedAsset $asset, FixedAssetService $service): RedirectResponse
    {
        abort_unless($asset->company_id === $request->user()?->company_id, 404);
        $data = $request->validate(['period_month' => ['required', 'date_format:Y-m']]);
        $service->postDepreciation($asset, $data['period_month'], 'depreciation-'.$asset->id.'-'.$data['period_month']);

        return back()->with('success', 'Depreciation posted.');
    }
}
