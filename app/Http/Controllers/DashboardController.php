<?php

namespace App\Http\Controllers;

use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\HealthScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reports, HealthScoreService $health): Response
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('dashboard', [
            'kpis' => $reports->kpis($request),
            'health' => $companyId ? $health->calculate($companyId, $request->user()?->branch_id) : null,
        ]);
    }
}
