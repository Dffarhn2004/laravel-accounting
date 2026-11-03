<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Budget;
use App\Services\Accounting\BudgetPlanService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Anggaran: Input (form), Data (register), Rekap (matriks Laba Rugi) and Kinerja (anggaran vs aktual). */
class BudgetController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    private function branches(Request $request, int $companyId)
    {
        return Branch::where('company_id', $companyId)->where('is_active', true)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function branchRule(int $companyId): array
    {
        return ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)];
    }

    /** A branch-bound user is always limited to their own branch. */
    private function scope(Request $request, mixed $requested): ?int
    {
        $branch = $request->user()->branch_id ?: $requested;

        return $branch ? (int) $branch : null;
    }

    private function budget(Request $request, int $companyId, int|string $id): Budget
    {
        return Budget::where('company_id', $companyId)
            ->when($request->user()->branch_id, fn ($q, $branch) => $q->where('branch_id', $branch))
            ->findOrFail($id);
    }

    public function index(Request $request, BudgetPlanService $plans): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'edit' => ['nullable', 'integer']]);
        $year = (int) ($f['year'] ?? now()->year);
        $edit = isset($f['edit']) ? $this->budget($request, $companyId, $f['edit']) : null;
        if ($edit) {
            $year = (int) $edit->fiscal_year;
        }
        $rows = $plans->rows($companyId, $year, $this->scope($request, null));

        return Inertia::render('accounting/budget', [
            'year' => $year,
            'accounts' => Account::where('company_id', $companyId)->whereIn('type', ['income', 'expense'])->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'branches' => $this->branches($request, $companyId),
            'summary' => $plans->branchSummary($rows),
            'editing' => $edit ? collect($rows)->firstWhere('id', $edit->id) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $companyId): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)->when($request->user()->branch_id, fn ($r, $b) => $r->where('id', $b))],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)->whereIn('type', ['income', 'expense'])],
            'activity' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'quantities' => ['required', 'array', 'size:12'],
            'quantities.*' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);
    }

    public function store(Request $request, BudgetPlanService $plans): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $this->validated($request, $companyId);
        $budget = $plans->save($companyId, $data);

        return to_route('anggaran.data', ['year' => $budget->fiscal_year])->with('success', 'Anggaran disimpan.');
    }

    public function update(Request $request, string $budget, BudgetPlanService $plans): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $model = $this->budget($request, $companyId, $budget);
        $saved = $plans->save($companyId, $this->validated($request, $companyId), $model);

        return to_route('anggaran.data', ['year' => $saved->fiscal_year])->with('success', 'Anggaran diperbarui.');
    }

    public function destroy(Request $request, string $budget): RedirectResponse
    {
        $model = $this->budget($request, $this->companyId($request), $budget);
        $year = $model->fiscal_year;
        $model->delete();

        return to_route('anggaran.data', ['year' => $year])->with('success', 'Anggaran dihapus.');
    }

    /** @return array{year:int, branch_id:?int, search:string} */
    private function registerFilters(Request $request, int $companyId): array
    {
        $f = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'branch_id' => $this->branchRule($companyId), 'search' => ['nullable', 'string', 'max:100']]);

        return ['year' => (int) ($f['year'] ?? now()->year), 'branch_id' => $this->scope($request, $f['branch_id'] ?? null), 'search' => (string) ($f['search'] ?? '')];
    }

    public function data(Request $request, BudgetPlanService $plans): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->registerFilters($request, $companyId);

        return Inertia::render('accounting/ang-data', [
            'rows' => $plans->rows($companyId, $filters['year'], $filters['branch_id'], $filters['search']),
            'filters' => $filters,
            'branches' => $this->branches($request, $companyId),
        ]);
    }

    public function export(Request $request, BudgetPlanService $plans): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $filters = $this->registerFilters($request, $companyId);
        $rows = $plans->rows($companyId, $filters['year'], $filters['branch_id'], $filters['search']);
        $months = array_slice(\App\Services\Accounting\LedgerService::MONTHS, 1);

        return response()->streamDownload(function () use ($rows, $months) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, array_merge(BudgetPlanService::CSV_COLUMNS, array_merge(...array_map(fn ($m) => ["Qty $m", "Rp $m"], $months)), ['Total Qty', 'TOTAL']));
            foreach ($rows as $r) {
                $line = [Csv::cell($r['branch']), $r['code'], Csv::cell($r['name']), Csv::cell($r['activity']), Csv::cell($r['unit']), $r['rate']];
                for ($m = 1; $m <= 12; $m++) {
                    $line[] = $r['qty'][$m];
                    $line[] = $r['amount'][$m];
                }
                fputcsv($out, array_merge($line, [$r['total_qty'], $r['total']]));
            }
            fclose($out);
        }, 'data-anggaran-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function recap(Request $request, BudgetPlanService $plans): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'branch_id' => $this->branchRule($companyId), 'all' => ['nullable', 'boolean']]);
        $year = (int) ($f['year'] ?? now()->year);
        $branch = $this->scope($request, $f['branch_id'] ?? null);

        return Inertia::render('accounting/ang-rekap', [
            'report' => $plans->recap($companyId, $year, $branch, (bool) ($f['all'] ?? false)),
            'filters' => ['year' => $year, 'branch_id' => $branch, 'all' => (bool) ($f['all'] ?? false)],
            'branches' => $this->branches($request, $companyId),
        ]);
    }

    public function performance(Request $request, BudgetPlanService $plans): Response
    {
        $companyId = $this->companyId($request);
        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => $this->branchRule($companyId),
        ]);
        $from = $f['from'] ?? now()->startOfYear()->toDateString();
        $to = $f['to'] ?? now()->endOfMonth()->toDateString();
        $branch = $this->scope($request, $f['branch_id'] ?? null);

        return Inertia::render('accounting/ang-kinerja', [
            'report' => $plans->performance($companyId, $from, $to, $branch),
            'filters' => ['from' => Carbon::parse($from)->toDateString(), 'to' => Carbon::parse($to)->toDateString(), 'branch_id' => $branch],
            'branches' => $this->branches($request, $companyId),
        ]);
    }
}
