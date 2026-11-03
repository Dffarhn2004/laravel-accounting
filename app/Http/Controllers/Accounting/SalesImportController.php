<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\SalesImportRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\ImportBatch;
use App\Services\Accounting\SalesImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesImportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $this->page($request, $request->session()->get('import_preview'), $request->session()->get('status'));
    }

    /** @param  array<string, mixed>|null  $preview */
    private function page(Request $request, ?array $preview, ?string $status): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $accounts = Account::where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name', 'type']);

        return Inertia::render('accounting/impor', [
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get(['id', 'code', 'name']),
            'accounts' => $accounts,
            'defaults' => [
                'receipt_account_id' => $accounts->firstWhere('code', '1101')?->id,
                'revenue_account_id' => $accounts->firstWhere('code', '4101')?->id,
                'other_income_account_id' => $accounts->firstWhere('code', '4104')?->id,
                'admin_account_id' => $accounts->firstWhere('code', '5205')?->id,
            ],
            'preview' => $preview,
            'status' => $status,
            'history' => ImportBatch::where('company_id', $companyId)
                ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->latest()
                ->limit(50)
                ->get(['id', 'code', 'channel', 'original_name', 'order_count', 'skipped_count', 'total_amount', 'status', 'created_at'])
                ->map(fn (ImportBatch $batch) => [
                    'id' => $batch->id,
                    'code' => $batch->code,
                    'channel' => $batch->channel,
                    'file' => $batch->original_name,
                    'orders' => $batch->order_count,
                    'skipped' => $batch->skipped_count,
                    'total' => $batch->total_amount,
                    'status' => $batch->status,
                    'created_at' => $batch->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function templateCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');
            fputcsv($out, SalesImportService::HEADERS);
            fputcsv($out, SalesImportService::sampleRow());
            fclose($out);
        }, 'template-impor-penjualan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function templateXlsx(SalesImportService $imports): StreamedResponse
    {
        return response()->streamDownload(function () use ($imports) {
            $path = tempnam(sys_get_temp_dir(), 'impor');
            $imports->writeTemplate($path);
            readfile($path);
            unlink($path);
        }, 'template-impor-penjualan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(SalesImportRequest $request, SalesImportService $imports): Response|RedirectResponse
    {
        $scope = $this->scope($request);
        if ($scope instanceof RedirectResponse) {
            return $scope;
        }
        $file = $request->file('file');
        try {
            $preview = $imports->preview($file->getRealPath(), $file->getClientOriginalExtension(), $file->getClientOriginalName(), $scope);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return $this->page($request, $preview, null);
    }

    public function store(SalesImportRequest $request, SalesImportService $imports): RedirectResponse
    {
        $scope = $this->scope($request);
        if ($scope instanceof RedirectResponse) {
            return $scope;
        }
        $file = $request->file('file');
        try {
            $batch = $imports->post($file->getRealPath(), $file->getClientOriginalExtension(), $scope + [
                'channel' => $request->string('channel')->toString(),
                'original_name' => $file->getClientOriginalName(),
                'idempotency_key' => $request->string('idempotency_key')->toString(),
                'created_by' => $request->user()->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }
        $message = $batch->order_count.' invoice diposting';
        if ($batch->skipped_count > 0) {
            $message .= ', '.$batch->skipped_count.' No Order dilewati karena sudah ada';
        }

        return redirect()->route('inputan.impor')->with('status', $batch->code.': '.$message.'.');
    }

    public function reverse(Request $request, ImportBatch $batch, SalesImportService $imports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($batch->company_id === $user->company_id, 404);
        if ($user->branch_id && $batch->branch_id !== $user->branch_id) {
            abort(403);
        }
        try {
            $imports->reverse($batch, [
                'company_id' => $user->company_id,
                'reason' => 'Pembatalan batch impor',
                'created_by' => $user->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return redirect()->route('inputan.impor')->with('status', $batch->code.' dibatalkan. Jurnal dibalik, tidak dihapus.');
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function scope(SalesImportRequest $request): array|RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $branchId = (int) $request->input('branch_id');
        if (! Branch::where(['company_id' => $companyId, 'id' => $branchId, 'is_active' => true])->exists()) {
            return back()->withErrors(['branch_id' => 'Cabang tidak ditemukan.'])->withInput();
        }
        if ($user->branch_id && $branchId !== $user->branch_id) {
            return back()->withErrors(['branch_id' => 'Anda hanya dapat mengimpor untuk cabang Anda sendiri.'])->withInput();
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'receipt_account_id' => (int) $request->input('receipt_account_id'),
            'revenue_account_id' => (int) $request->input('revenue_account_id'),
            'other_income_account_id' => (int) $request->input('other_income_account_id'),
            'admin_account_id' => (int) $request->input('admin_account_id'),
        ];
    }
}
