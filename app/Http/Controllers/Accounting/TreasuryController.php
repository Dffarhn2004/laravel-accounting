<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\FiscalPeriod;
use App\Models\TreasuryTransaction;
use App\Services\Accounting\TreasuryPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TreasuryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

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
