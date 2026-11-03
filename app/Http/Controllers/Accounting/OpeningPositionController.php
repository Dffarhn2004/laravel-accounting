<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\FiscalPeriod;
use App\Models\OpeningBalance;
use App\Models\TemplateOption;
use App\Services\Accounting\OpeningPositionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Saldo Neraca Akhir Periode (#/saldo) and Input Setoran Modal Awal (#/modal).
 * Two pages, one opening per branch + fiscal period (see OpeningPositionService).
 */
class OpeningPositionController extends Controller
{
    private const AMOUNT = ['nullable', 'regex:/^\d{1,18}(\.\d{1,2})?$/'];

    private function companyId(Request $request): int
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return $companyId;
    }

    private function shared(int $companyId, string $method): array
    {
        return [
            'branches' => Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'fiscalPeriods' => FiscalPeriod::where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            // Every opening (any method) so the UI can show why a branch is locked.
            'openings' => OpeningBalance::where('company_id', $companyId)->whereNull('reversed_at')->with('branch:id,name')->latest('posted_on')->get(['id', 'branch_id', 'fiscal_period_id', 'method', 'posted_on', 'journal_entry_id']),
            'method' => $method,
        ];
    }

    public function saldo(Request $request): Response
    {
        $companyId = $this->companyId($request);

        return Inertia::render('accounting/saldo-neraca', $this->shared($companyId, OpeningPositionService::SALDO_NERACA) + [
            'accounts' => Account::where('company_id', $companyId)->where('is_active', true)->whereIn('type', ['asset', 'liability', 'equity'])->orderBy('code')->get(['id', 'code', 'name', 'type', 'is_contra']),
        ]);
    }

    public function storeSaldo(Request $request, OpeningPositionService $service): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate($this->commonRules($companyId) + [
            'items.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'items.*.amount' => self::AMOUNT,
        ]);

        $service->postBalanceSheet($data + ['company_id' => $companyId, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Saldo neraca awal diposting.');
    }

    public function modal(Request $request): Response
    {
        $companyId = $this->companyId($request);
        $allowed = TemplateOption::where(['company_id' => $companyId, 'group' => 'opening_account', 'is_active' => true])->pluck('account_id')->filter();
        $accounts = Account::where('company_id', $companyId)->where('is_active', true)->whereIn('type', ['asset', 'liability'])
            ->when($allowed->isNotEmpty(), fn ($q) => $q->whereIn('id', $allowed))->orderBy('code')->get(['id', 'code', 'name', 'type', 'is_contra']);

        return Inertia::render('accounting/modal-awal', $this->shared($companyId, OpeningPositionService::MODAL_AWAL) + [
            'accounts' => $accounts,
            // Fixed-asset classes need acquisition info and carry a default useful life.
            'assetClasses' => AssetCategory::where('company_id', $companyId)->where('useful_life_years', '>', 0)->get(['asset_account_id', 'useful_life_years', 'accumulated_account_id']),
            'recorded' => OpeningBalance::where(['company_id' => $companyId, 'method' => OpeningPositionService::MODAL_AWAL])->whereNull('reversed_at')
                ->with(['branch:id,name', 'lines.account:id,code,name'])->latest('posted_on')->get(),
        ]);
    }

    public function storeModal(Request $request, OpeningPositionService $service): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $data = $request->validate($this->commonRules($companyId) + [
            'items.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => self::AMOUNT,
            'items.*.acquired_on' => ['nullable', 'date'],
            'items.*.useful_life_years' => ['nullable', 'integer', 'between:1,80'],
            'items.*.accumulated' => self::AMOUNT,
        ]);

        $service->postModalAwal($data + ['company_id' => $companyId, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Setoran modal awal diposting.');
    }

    private function commonRules(int $companyId): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'fiscal_period_id' => ['required', 'integer', Rule::exists('fiscal_periods', 'id')->where('company_id', $companyId)],
            'posted_on' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
        ];
    }
}
