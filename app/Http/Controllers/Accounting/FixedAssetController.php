<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AssetAcquisitionRequest;
use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\TemplateOption;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\FixedAssetService;
use App\Support\Accounting\Periods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FixedAssetController extends Controller
{
    public function index(Request $request, FixedAssetService $assets): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return Inertia::render('accounting/fixed-assets', [
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get(['id', 'code', 'name']),
            'fiscalPeriods' => FiscalPeriod::where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'paymentMethods' => TemplateOption::query()->where(['company_id' => $companyId, 'group' => 'outflow_payment', 'is_active' => true])->with('account:id,type')->orderBy('sort_order')->get(['id', 'code', 'label', 'account_id'])
                ->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'label' => $o->label, 'settles' => $o->account?->type === 'asset' ? 'now' : ($o->account?->type === 'liability' ? 'debt' : null)])->values(),
            'categories' => AssetCategory::where('company_id', $companyId)->with('assetAccount:id,code,name')->get()
                ->sortBy(fn ($c) => $c->assetAccount?->code)->values()
                ->map(fn ($c) => ['asset_account_id' => $c->asset_account_id, 'code' => $c->assetAccount?->code, 'name' => $c->assetAccount?->name, 'useful_life_years' => (int) $c->useful_life_years])->values(),
            'nextNumber' => $assets->nextReference($companyId, now()->toDateString()),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function store(AssetAcquisitionRequest $request, FixedAssetService $assets, AttachmentService $attachments): RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);

        if ($user->branch_id && (int) $data['branch_id'] !== $user->branch_id) {
            return back()->withErrors(['branch_id' => 'Anda hanya dapat mencatat untuk cabang Anda sendiri.'])->withInput();
        }
        $period = Periods::openFor($companyId, $data['acquired_on']);
        if (! $period) {
            return back()->withErrors(['acquired_on' => 'Tidak ada tahun buku terbuka untuk tanggal ini.'])->withInput();
        }
        $option = TemplateOption::where(['company_id' => $companyId, 'group' => 'outflow_payment', 'code' => $data['payment_method']])->first();
        $account = $option?->account_id ? Account::where('company_id', $companyId)->find($option->account_id) : null;
        if (! $account || ! in_array($account->type, ['asset', 'liability'], true)) {
            return back()->withErrors(['payment_method' => 'Jenis pembayaran belum dipetakan ke akun kas/bank/hutang (Template Jurnal bagian 7).'])->withInput();
        }

        try {
            $asset = DB::transaction(function () use ($assets, $attachments, $data, $files, $companyId, $user, $period, $account) {
                $asset = $assets->acquire($data + [
                    'company_id' => $companyId,
                    'fiscal_period_id' => $period->id,
                    'settlement_account_id' => $account->id,
                    'name' => $data['description'],
                    'created_by' => $user->id,
                ]);
                if ($files) {
                    $attachments->attach($asset, $companyId, $files, $user->id);
                }

                return $asset;
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset' => $e->getMessage()])->withInput();
        }

        return redirect()->route('data.assets.index', ['search' => $asset->code])->with('success', 'Aset '.$asset->code.' diposting.');
    }

    public function depreciate(Request $request, FixedAsset $asset, FixedAssetService $assets): RedirectResponse
    {
        abort_unless($asset->company_id === $request->user()?->company_id, 404);
        abort_if($request->user()?->branch_id && $asset->branch_id !== $request->user()->branch_id, 404);
        $data = $request->validate(['period_month' => ['required', 'date_format:Y-m']]);

        try {
            $assets->postDepreciation($asset, $data['period_month'], 'depreciation-'.$asset->id.'-'.$data['period_month']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyusutan diposting.');
    }
}
