<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Services\Accounting\AgingReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgingController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{as_of:?string, customer_id:?int, branch_id:?int, status:?string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'status' => ['nullable', Rule::in(['open', 'paid', 'all'])],
        ]);
        $branch = $request->user()->branch_id ?: ($data['branch_id'] ?? null);

        return [
            'as_of' => $data['as_of'] ?? now()->toDateString(),
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'branch_id' => $branch ? (int) $branch : null,
            'status' => $data['status'] ?? 'open',
        ];
    }

    public function index(Request $request, AgingReportService $aging): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $report = $aging->build($filters + ['company_id' => $companyId]);

        return Inertia::render('accounting/aging', [
            'rows' => $report['rows'],
            'buckets' => $report['buckets'],
            'reconciliation' => $report['reconciliation'],
            'columns' => AgingReportService::COLUMNS,
            'filters' => $filters,
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function export(Request $request, AgingReportService $aging): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $report = $aging->build($this->filters($request, $companyId) + ['company_id' => $companyId]);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, AgingReportService::COLUMNS);
            foreach ($report['rows'] as $row) {
                fputcsv($out, AgingReportService::csvRow($row));
            }
            fclose($out);
        }, 'laporan-piutang-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
