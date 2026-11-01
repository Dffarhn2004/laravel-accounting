<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ProfitLossService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Financial statements: Laba Rugi (konsolidasi and per cabang), Neraca and Cashflow.
 * All figures come from the journal through the report services; the controller only validates
 * filters, applies branch scope and shapes the response.
 */
class ReportController extends Controller
{
    public function index(Request $request, FinancialReportService $reports)
    {
        return Inertia::render('accounting/reports', $reports->summary($request));
    }

    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    private function branches(Request $request, int $companyId)
    {
        $restrict = $request->user()->branch_id;

        return Branch::where('company_id', $companyId)->when($restrict, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function branchRule(int $companyId): array
    {
        return ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)];
    }

    /** Branch actually applied: a branch-bound user is always limited to their own. */
    private function branchScope(Request $request, ?int $requested): ?int
    {
        return $request->user()->branch_id ?? $requested;
    }

    /** Laba Rugi Konsolidasi: twelve months of one year, all branches or one. */
    public function profitLoss(Request $request, ProfitLossService $service): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'branch_id' => $this->branchRule($companyId), 'all' => ['nullable', 'boolean']]);
        $year = (int) ($f['year'] ?? now()->year);

        return Inertia::render('accounting/profit-loss', [
            'report' => $service->monthly($companyId, $year, $this->branchScope($request, $f['branch_id'] ?? null), (bool) ($f['all'] ?? false)),
            'filters' => ['year' => $year, 'branch_id' => $f['branch_id'] ?? null, 'all' => (bool) ($f['all'] ?? false)],
            'branches' => $this->branches($request, $companyId),
        ]);
    }

    /** Laba Rugi per Cabang: branches side by side for any period; TOTAL equals the consolidated figures. */
    public function branchProfitLoss(Request $request, ProfitLossService $service): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'all' => ['nullable', 'boolean']]);
        $from = $f['from'] ?? now()->startOfYear()->toDateString();
        $to = $f['to'] ?? now()->endOfYear()->toDateString();

        return Inertia::render('accounting/branch-profit-loss', [
            'report' => $service->byBranch($companyId, $from, $to, $request->user()->branch_id, (bool) ($f['all'] ?? false)),
            'filters' => ['from' => $from, 'to' => $to, 'all' => (bool) ($f['all'] ?? false)],
        ]);
    }

    public function balanceSheet(Request $request, BalanceSheetService $service): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate([
            'branch_id' => $this->branchRule($companyId),
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'all' => ['nullable', 'boolean'],
        ]);
        $to = $f['to'] ?? now()->toDateString();
        $from = $f['from'] ?? Carbon::parse($to)->startOfYear()->toDateString();

        return Inertia::render('accounting/balance-sheet', [
            'report' => $service->report($companyId, $from, $to, $this->branchScope($request, $f['branch_id'] ?? null), (bool) ($f['all'] ?? false)),
            'filters' => ['branch_id' => $f['branch_id'] ?? null, 'from' => $from, 'to' => $to, 'all' => (bool) ($f['all'] ?? false)],
            'branches' => $this->branches($request, $companyId),
        ]);
    }

    public function cashFlow(Request $request, CashFlowService $service): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate(['branch_id' => $this->branchRule($companyId), 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = $f['to'] ?? now()->toDateString();
        $from = $f['from'] ?? Carbon::parse($to)->startOfYear()->toDateString();

        return Inertia::render('accounting/cash-flow', [
            'report' => $service->report($companyId, $from, $to, $this->branchScope($request, $f['branch_id'] ?? null)),
            'filters' => ['branch_id' => $f['branch_id'] ?? null, 'from' => $from, 'to' => $to],
            'branches' => $this->branches($request, $companyId),
        ]);
    }
}
