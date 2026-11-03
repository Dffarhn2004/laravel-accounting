<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CashBankRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\FiscalPeriod;
use App\Models\TemplateOption;
use App\Models\TreasuryTransaction;
use App\Services\Accounting\AttachmentService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\TreasuryPostingService;
use App\Support\Accounting\Periods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use InvalidArgumentException;

class TreasuryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return Inertia::render('accounting/treasury', [
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->with('account')
                ->orderBy('name')
                ->get(),
            'accounts' => Account::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($branchId, fn ($query) => $query->whereKey($branchId))
                ->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->orderBy('starts_on')
                ->get(['id', 'starts_on', 'ends_on']),
            'transactions' => TreasuryTransaction::query()
                ->where('company_id', $companyId)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->with(['cashAccount', 'journalEntry'])
                ->latest('posted_on')
                ->paginate(25)
                ->withQueryString(),
            'reconciliation' => $this->reconciliation($companyId, $branchId),
        ]);
    }

    private function reconciliation(int $companyId, ?int $branchId): array
    {
        $transactions = TreasuryTransaction::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'posted')
            ->get();

        $treasury = (float) $transactions->sum(fn ($transaction) => $transaction->direction === 'out'
            ? -$transaction->amount
            : $transaction->amount);

        return ['treasury' => $treasury, 'gl' => null, 'difference' => null];
    }

    /** PRD form: 11 treasury types, Dari/Ke limited by Template Jurnal. */
    public function create(Request $request, TreasuryPostingService $treasury)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        $types = TemplateOption::query()
            ->where(['company_id' => $companyId, 'group' => 'treasury_type', 'is_active' => true])
            ->orderBy('sort_order')
            ->get();
        $categoryOptions = TemplateOption::query()
            ->where(['company_id' => $companyId, 'group' => 'treasury_category', 'is_active' => true])
            ->with('account:id,code,name,type,is_contra,is_active')
            ->orderBy('sort_order')
            ->get();

        $accounts = [];
        foreach ($categoryOptions as $option) {
            $account = $option->account;
            if (! $account || ! $account->is_active) {
                continue;
            }
            $accounts[$account->id] ??= [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'debit_normal' => LedgerService::debitNormal($account->type, (bool) $account->is_contra),
                'categories' => [],
            ];
            $category = $option->meta['category'] ?? null;
            if ($category && ! in_array($category, $accounts[$account->id]['categories'], true)) {
                $accounts[$account->id]['categories'][] = $category;
            }
        }

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/kas-bank-form', [
            'types' => $types->map(fn ($type) => [
                'code' => $type->code,
                'label' => $type->label,
                'from_category' => $type->meta['from_category'] ?? '',
                'to_category' => $type->meta['to_category'] ?? '',
            ])->values(),
            'accounts' => array_values($accounts),
            'balances' => $treasury->positions($companyId, array_keys($accounts), $branches->pluck('id')->all()),
            'branches' => $branches,
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function store(CashBankRequest $request, TreasuryPostingService $service, AttachmentService $attachments): RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);
        $data['amount'] = bcadd((string) $data['from_amount'], '0', 2);
        unset($data['from_amount'], $data['to_amount']);

        if ($user->branch_id && (int) $data['branch_id'] !== (int) $user->branch_id) {
            return back()->withErrors(['branch_id' => 'Anda hanya dapat mencatat untuk cabang Anda sendiri.'])->withInput();
        }
        $period = Periods::openFor($companyId, $data['posted_on']);
        if (! $period) {
            return back()->withErrors(['posted_on' => 'Tidak ada tahun buku terbuka untuk tanggal ini.'])->withInput();
        }

        try {
            $transaction = DB::transaction(function () use ($service, $attachments, $data, $files, $period, $companyId, $user) {
                $transaction = $service->move($data + ['company_id' => $companyId, 'fiscal_period_id' => $period->id, 'created_by' => $user->id]);
                if ($files) {
                    $attachments->attach($transaction, $companyId, $files, $user->id);
                }

                return $transaction;
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cashbank' => $e->getMessage()])->withInput();
        }

        return redirect()->route('data.kasbank.index')->with('success', 'Kas/bank '.$transaction->reference.' diposting.');
    }

    public function movement(Request $request, TreasuryPostingService $service): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'counter_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'direction' => ['required', 'in:in,out'],
            'posted_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:150'],
        ]) + ['company_id' => $user->company_id];

        $cashAccount = CashAccount::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['cash_account_id']);
        $data['lines'] = $data['direction'] === 'in'
            ? [
                ['account_id' => $cashAccount->account_id, 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['counter_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]
            : [
                ['account_id' => $data['counter_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $cashAccount->account_id, 'debit' => '0', 'credit' => $data['amount']],
            ];

        $service->cashMovement($data);

        return back()->with('success', 'Treasury movement posted.');
    }

    public function transfer(Request $request, TreasuryPostingService $service): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'from_cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id', 'different:to_cash_account_id'],
            'to_cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'posted_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:150'],
        ]) + ['company_id' => $user->company_id];

        $service->transfer($data);

        return back()->with('success', 'Cash transfer posted.');
    }
}
