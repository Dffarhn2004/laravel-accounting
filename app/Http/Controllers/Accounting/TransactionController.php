<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Http\Requests\Accounting\ExpenseRequest;
use App\Http\Requests\Accounting\InvoiceRequest;
use App\Http\Requests\Accounting\ReceiptRequest;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function storeExpense(ExpenseRequest $request, TransactionPostingService $service): RedirectResponse
    {
        $service->expense($request->validated());

        return back()->with('success', 'Expense posted.');
    }

    public function storeInvoice(InvoiceRequest $request, TransactionPostingService $service): RedirectResponse
    {
        $service->invoice($request->validated());

        return back()->with('success', 'Invoice posted.');
    }

    public function storeReceipt(ReceiptRequest $request, TransactionPostingService $service): RedirectResponse
    {
        $service->receipt($request->validated());

        return back()->with('success', 'Receipt posted.');
    }

    public function index(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/transactions', [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'expenses' => Expense::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest('expense_date')
                ->paginate(25, ['*'], 'expenses_page'),
            'invoices' => Invoice::query()
                ->with('customer')
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest('issued_on')
                ->paginate(25, ['*'], 'invoices_page'),
        ]);
    }

    public function invoices(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/invoices', [
            'showForm' => false,
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'invoices' => Invoice::query()->with('customer')->where('company_id', $companyId)->latest('issued_on')->paginate(25)->withQueryString(),
        ]);
    }

    public function createInvoice(Request $request)
    {
        $companyId = $request->user()?->company_id;
        return Inertia::render('accounting/invoices', [
            'showForm' => true,
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'invoices' => Invoice::query()->where('company_id', $companyId)->paginate(1),
        ]);
    }

    public function expenses(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/expenses', [
            'showForm' => false,
            'expenses' => Expense::query()->with('vendor')->where('company_id', $companyId)->latest('expense_date')->paginate(25)->withQueryString(),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
        ]);
    }

    public function createExpense(Request $request)
    {
        $companyId = $request->user()?->company_id;
        return Inertia::render('accounting/expenses', [
            'showForm' => true,
            'expenses' => Expense::query()->where('company_id', $companyId)->paginate(1),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
        ]);
    }

    public function receivables(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/receivables', [
            'invoices' => Invoice::query()->where('company_id', $companyId)->with(['customer', 'receiptAllocations'])->whereIn('status', ['unpaid', 'partial'])->latest('issued_on')->get(),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
        ]);
    }

    public function showInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->company_id === $request->user()?->company_id, 404);

        return Inertia::render('accounting/invoice-detail', [
            'invoice' => $invoice->load(['customer', 'lines', 'receiptAllocations.receipt']),
            'journalEntry' => JournalEntry::query()
                ->where('company_id', $invoice->company_id)
                ->where('source_type', TransactionPostingService::class)
                ->where('source_id', (string) $invoice->id)
                ->with('lines.account')
                ->first(),
        ]);
    }
}
