<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Accounting\TaxReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReportController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{year:int, branch_id:?int, tax:string, month:?int, status:string} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'tax' => ['nullable', Rule::in(['all', ...array_keys(TaxReportService::TAXES)])],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', Rule::in(['all', 'open', 'paid', 'overdue'])],
        ]);
        $branch = $request->user()->branch_id ?: ($data['branch_id'] ?? null);

        return [
            'year' => (int) ($data['year'] ?? now()->year),
            'branch_id' => $branch ? (int) $branch : null,
            'tax' => $data['tax'] ?? 'all',
            'month' => isset($data['month']) ? (int) $data['month'] : null,
            'status' => $data['status'] ?? 'all',
        ];
    }

    public function index(Request $request, TaxReportService $report): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);

        return Inertia::render('accounting/lap-pajak', $report->build($filters + ['company_id' => $companyId]) + [
            'filters' => $filters,
            'taxes' => collect(TaxReportService::TAXES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label']])->values(),
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'columns' => TaxReportService::COLUMNS,
        ]);
    }

    public function export(Request $request, TaxReportService $report): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $built = $report->build($this->filters($request, $companyId) + ['company_id' => $companyId]);

        return response()->streamDownload(function () use ($built) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, TaxReportService::COLUMNS);
            foreach ($built['masa'] as $row) {
                fputcsv($out, array_map(fn ($v) => Csv::cell((string) $v), TaxReportService::csvRow($row)));
            }
            fclose($out);
        }, 'laporan-pajak-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
