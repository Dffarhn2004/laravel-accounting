<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Account;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('accounting/budget', [
            'budgets' => Budget::query()
                ->with('account', 'branch', 'monthlyLines')
                ->when($request->integer('company_id'), fn ($q, $id) => $q->where('company_id', $id))
                ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'accounts' => Account::query()->where('company_id', $request->user()?->company_id)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $request->user()?->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ratios' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'activity' => ['nullable', 'string', 'max:255'],
            'monthly_amounts' => ['required', 'array', 'size:12'],
            'monthly_amounts.*' => ['required', 'numeric', 'min:0'],
        ]);
        $companyId = $request->user()->company_id;
        $budget = Budget::create(collect($data)->except('monthly_amounts')->all() + ['company_id' => $companyId, 'status' => 'draft']);
        $budget->monthlyLines()->createMany(collect($data['monthly_amounts'])->values()->map(fn ($amount, $index) => ['month' => $index + 1, 'amount' => $amount])->all());

        return back()->with('success', 'Budget created.');
    }
}
