<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AgingReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgingController extends Controller
{
    public function index(Request $request, AgingReportService $aging)
    {
        return Inertia::render('accounting/aging', [
            'receivables' => $aging->receivables($request->only(['company_id', 'branch_id', 'as_of'])),
            'payables' => $aging->payables($request->only(['company_id', 'branch_id', 'as_of'])),
        ]);
    }
}
