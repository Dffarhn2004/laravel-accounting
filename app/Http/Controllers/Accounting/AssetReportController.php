<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetCategory;
use App\Services\Accounting\AssetReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetReportController extends Controller
{
    public function index(Request $request, AssetReportService $report): Response
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'source' => ['nullable', Rule::in(['purchase', 'opening'])],
            'status' => ['nullable', Rule::in(['active', 'reversed'])],
        ]);
        $filters = [
            'as_of' => $data['as_of'] ?? now()->toDateString(),
            'account_id' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'source' => $data['source'] ?? null,
            'status' => $data['status'] ?? null,
        ];

        return Inertia::render('accounting/lap-asset', [
            'filters' => $filters,
            'report' => $report->build($companyId, $request->user()->branch_id, $filters),
            'accounts' => Account::where('company_id', $companyId)->whereIn('id', AssetCategory::where('company_id', $companyId)->pluck('asset_account_id'))->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
