<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Services\Accounting\PayableReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayableReportController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{as_of:string, account_id:?int, branch_id:?int, status:string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'liability'))],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'status' => ['nullable', Rule::in(['open', 'paid', 'all'])],
        ]);
        $branch = $request->user()->branch_id ?: ($data['branch_id'] ?? null);

        return [
            'as_of' => $data['as_of'] ?? now()->toDateString(),
            'account_id' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'branch_id' => $branch ? (int) $branch : null,
            'status' => $data['status'] ?? 'open',
        ];
    }

    public function index(Request $request, PayableReportService $report): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $built = $report->build($filters + ['company_id' => $companyId]);

        return Inertia::render('accounting/payables', [
            'rows' => $built['rows'],
            'reconciliation' => $built['reconciliation'],
            'columns' => PayableReportService::COLUMNS,
            'filters' => $filters,
            'accounts' => Account::where('company_id', $companyId)->where('type', 'liability')->whereIn('module', ['Hutang', 'Pinjaman'])->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function export(Request $request, PayableReportService $report): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $built = $report->build($this->filters($request, $companyId) + ['company_id' => $companyId]);

        return response()->streamDownload(function () use ($built) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, PayableReportService::COLUMNS);
            foreach ($built['rows'] as $row) {
                fputcsv($out, PayableReportService::csvRow($row));
            }
            fclose($out);
        }, 'laporan-hutang-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
