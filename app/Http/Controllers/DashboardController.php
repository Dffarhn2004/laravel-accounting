<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Accounting\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard: six KPIs, trends, composition, branch performance, top 5 AR/AP and the eight
 * integrity controls (see DashboardService). Branch and period filters are applied server side
 * to every figure; a branch-bound user can never widen the scope.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;

        if (! $companyId) {
            return Inertia::render('dashboard', ['dashboard' => null, 'filters' => null, 'branches' => []]);
        }

        $f = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = $f['to'] ?? now()->toDateString();
        $from = $f['from'] ?? now()->parse($to)->startOfYear()->toDateString();
        $scope = $user->branch_id ?? ($f['branch_id'] ?? null);

        return Inertia::render('dashboard', [
            'dashboard' => $dashboard->build($companyId, $from, $to, $scope, $user->branch_id),
            'filters' => ['branch_id' => $scope, 'from' => $from, 'to' => $to],
            'branches' => Branch::where('company_id', $companyId)->when($user->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
