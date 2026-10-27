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
        $filters = [
            'company_id' => $request->user()?->company_id,
            'branch_id' => $request->user()?->branch_id,
            'as_of' => $request->date('as_of')?->toDateString(),
        ];

        return Inertia::render('accounting/aging', [
            'receivables' => $aging->receivables($filters),
            'payables' => $aging->payables($filters),
            'filters' => ['as_of' => $filters['as_of']],
        ]);
    }
}
