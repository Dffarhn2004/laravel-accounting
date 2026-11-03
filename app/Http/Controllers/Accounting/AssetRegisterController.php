<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Services\Accounting\AssetRegisterService;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\FixedAssetService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetRegisterController extends Controller
{
    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    /** @return array{search:?string, branch_id:?int} */
    private function filters(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
        ]);

        return ['search' => $data['search'] ?? null, 'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null];
    }

    private function authorizeAsset(Request $request, FixedAsset $asset): void
    {
        $user = $request->user();
        abort_unless($asset->company_id === $user->company_id, 404);
        abort_if($user->branch_id && $asset->branch_id !== $user->branch_id, 404);
    }

    public function index(Request $request, AssetRegisterService $register): Response
    {
        $companyId = $this->companyId($request);
        $filters = $this->filters($request, $companyId);
        $query = $register->query($companyId, $filters, $request->user()->branch_id);
        $page = $query->clone()->paginate(AssetRegisterService::PER_PAGE)->withQueryString();
        $startNo = ($page->currentPage() - 1) * AssetRegisterService::PER_PAGE + 1;

        return Inertia::render('accounting/data-asset', [
            'rows' => $register->rows($page->getCollection(), $startNo),
            'pagination' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'totals' => $register->totals($query),
            'filters' => $filters,
            'columns' => AssetRegisterService::COLUMNS,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function export(Request $request, AssetRegisterService $register): StreamedResponse
    {
        $companyId = $this->companyId($request);
        $query = $register->query($companyId, $this->filters($request, $companyId), $request->user()->branch_id);

        return response()->streamDownload(function () use ($query, $register) {
            $out = fopen('php://output', 'w');
            Csv::begin($out, [...AssetRegisterService::COLUMNS, 'Status']);
            $no = 1;
            $query->clone()->chunkById(200, function ($assets) use ($out, $register, &$no) {
                foreach ($register->rows($assets, $no) as $row) {
                    fputcsv($out, AssetRegisterService::csvRow($row));
                    $no = $row['no'] + 1;
                }
            }, 'id', 'id');
            fclose($out);
        }, 'data-asset-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reverse(Request $request, FixedAsset $asset, FixedAssetService $assets): RedirectResponse
    {
        $this->authorizeAsset($request, $asset);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500'], 'posted_on' => ['nullable', 'date_format:Y-m-d']]);

        try {
            $assets->reverseAcquisition($asset, $data + ['company_id' => $asset->company_id, 'created_by' => $request->user()->id]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembelian '.$asset->code.' dibalik. Jurnal asli tetap tersimpan.');
    }

    public function reverseDepreciation(Request $request, FixedAsset $asset, FixedAssetService $assets): RedirectResponse
    {
        $this->authorizeAsset($request, $asset);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500'], 'posted_on' => ['nullable', 'date_format:Y-m-d']]);

        try {
            $assets->reverseDepreciation($asset, $data + ['company_id' => $asset->company_id, 'created_by' => $request->user()->id]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyusutan terakhir '.$asset->code.' dibalik.');
    }

    public function attach(Request $request, FixedAsset $asset, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeAsset($request, $asset);
        $request->validate(AttachmentService::rules() + ['attachments' => ['required', 'array', 'min:1', 'max:'.AttachmentService::MAX_FILES]]);
        DB::transaction(fn () => $attachments->attach($asset, $asset->company_id, $request->file('attachments', []), $request->user()->id));

        return back()->with('success', 'Bukti ditambahkan.');
    }
}
