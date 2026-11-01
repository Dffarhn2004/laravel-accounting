<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TemplateOption;
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
use App\Services\Accounting\AttachmentService;
use App\Support\Accounting\Periods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function storeExpense(ExpenseRequest $request, TransactionPostingService $service, AttachmentService $attachments): RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);

        if ($user->branch_id && (int) $data['branch_id'] !== $user->branch_id) {
            return back()->withErrors(['branch_id' => 'Anda hanya dapat mencatat untuk cabang Anda sendiri.'])->withInput();
        }
        $period = Periods::openFor($companyId, $data['expense_date']);
        if (! $period) {
            return back()->withErrors(['expense_date' => 'Tidak ada tahun buku terbuka untuk tanggal ini.'])->withInput();
        }

        // Cash/bank settles now (asset); a payable (liability) leaves the expense open as debt.
        $option = TemplateOption::where(['company_id' => $companyId, 'group' => 'outflow_payment', 'code' => $data['payment_method']])->first();
        $account = $option?->account_id ? Account::where('company_id', $companyId)->find($option->account_id) : null;
        if (! $account || ! in_array($account->type, ['asset', 'liability'], true)) {
            return back()->withErrors(['payment_method' => 'Jenis pembayaran belum dipetakan ke akun kas/bank/hutang (Template Jurnal bagian 7).'])->withInput();
        }
        $data[$account->type === 'asset' ? 'payment_account_id' : 'payable_account_id'] = $account->id;

        try {
            $expense = DB::transaction(function () use ($service, $attachments, $data, $files, $period, $companyId, $user) {
                $expense = $service->expense($data + ['company_id' => $companyId, 'fiscal_period_id' => $period->id, 'created_by' => $user->id]);
                if ($files) {
                    $attachments->attach($expense, $companyId, $files, $user->id);
                }

                return $expense;
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['expense' => $e->getMessage()])->withInput();
        }

        return redirect()->route('transactions.expenses.show', $expense)->with('success', 'Beban '.$expense->reference.' diposting.');
    }

    public function storeInvoice(InvoiceRequest $request, TransactionPostingService $service, AttachmentService $attachments): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('attachments', []);
        unset($data['attachments']);

        // Posting and evidence succeed or fail together.
        try {
            $invoice = DB::transaction(function () use ($service, $attachments, $data, $files, $request) {
                $invoice = $service->invoice($data);
                if ($files) {
                    $attachments->attach($invoice, $invoice->company_id, $files, $request->user()->id);
                }

                return $invoice;
            });
        } catch (\InvalidArgumentException $e) {
            // Business-rule failures (overpayment, closed period, wrong account type, duplicate submit).
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('transactions.invoices.show', $invoice)->with('success', 'Invoice '.$invoice->invoice_number.' diposting.');
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

    public function createInvoice(Request $request, TransactionPostingService $service)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $company = $user->company;
        $codes = Account::where('company_id', $companyId)->whereIn('code', ['1201', '2301', '5103', '1101', '1111'])->pluck('id', 'code');

        return Inertia::render('accounting/invoice-form', [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get(['id', 'code', 'name']),
            'customers' => Customer::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('sku')->get(['id', 'sku', 'name', 'unit', 'price', 'revenue_account_id']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'paymentMethods' => TemplateOption::query()->where(['company_id' => $companyId, 'group' => 'invoice_payment', 'is_active' => true])->orderBy('sort_order')->get(['id', 'code', 'label', 'account_id']),
            'defaults' => [
                'receivable_account_id' => $codes['1201'] ?? null,
                'vat_output_account_id' => $codes['2301'] ?? null,
                'discount_account_id' => $codes['5103'] ?? null,
                'vat_rate' => $company->default_vat_rate,
                'pph23_rate' => $company->default_pph23_rate,
            ],
            'company' => $company->only(['name', 'address', 'phone', 'email', 'invoice_bank_name', 'invoice_bank_account_name', 'invoice_bank_account_number']),
            'nextNumber' => $service->nextInvoiceNumber($companyId, now()->toDateString()),
        ]);
    }

    public function createExpense(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');

        return Inertia::render('accounting/expense-form', [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_active', true)->where('type', 'expense')->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('company_id', $companyId)->where('is_active', true)->when($user->branch_id, fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get(['id', 'code', 'name']),
            'fiscalPeriods' => FiscalPeriod::query()->where('company_id', $companyId)->where('status', 'open')->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on']),
            'paymentMethods' => TemplateOption::query()->where(['company_id' => $companyId, 'group' => 'outflow_payment', 'is_active' => true])->with('account:id,type')->orderBy('sort_order')->get(['id', 'code', 'label', 'account_id'])
                ->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'label' => $o->label, 'settles' => $o->account?->type === 'asset' ? 'now' : ($o->account?->type === 'liability' ? 'debt' : null)])->values(),
            'maxAttachments' => AttachmentService::MAX_FILES,
        ]);
    }

    public function showExpense(Request $request, Expense $expense)
    {
        abort_unless($expense->company_id === $request->user()?->company_id, 404);

        return Inertia::render('accounting/expense-detail', [
            'expense' => $expense->load(['vendor', 'payments', 'attachments', 'expenseAccount:id,code,name', 'settlementAccount:id,code,name', 'branch:id,name']),
            'journalEntry' => $expense->journal_entry_id
                ? JournalEntry::with('lines.account')->where('company_id', $expense->company_id)->find($expense->journal_entry_id)
                : null,
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
            'invoice' => $invoice->load(['customer', 'lines.revenueAccount', 'otherIncomes.account', 'attachments', 'receiptAllocations.receipt']),
            'journalEntry' => JournalEntry::query()
                ->where('company_id', $invoice->company_id)
                ->where('source_type', TransactionPostingService::class)
                ->where('source_id', (string) $invoice->id)
                ->with('lines.account')
                ->first(),
        ]);
    }

    /** A4 print view of an issued invoice (company identity and bank details included). */
    public function printInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->company_id === $request->user()?->company_id, 404);

        return Inertia::render('accounting/invoice-print', [
            'invoice' => $invoice->load(['customer', 'lines', 'otherIncomes']),
            'company' => $request->user()->company->only(['name', 'address', 'phone', 'email', 'invoice_bank_name', 'invoice_bank_account_name', 'invoice_bank_account_number']),
        ]);
    }
}
