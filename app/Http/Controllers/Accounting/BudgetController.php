<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Budget;
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
        ]);
    }
}
