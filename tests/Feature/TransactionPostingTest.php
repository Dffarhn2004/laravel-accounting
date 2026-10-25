<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_receipt_reduces_invoice_balance(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $customer = $company->customers()->create(['name' => 'Buyer', 'code' => 'BUY-1']);
        $ar = $company->accounts()->create(['code' => '1200', 'name' => 'AR', 'type' => 'asset']);
        $revenue = $company->accounts()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'income']);
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $service = app(TransactionPostingService::class);

        $invoice = $service->invoice([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'customer_id' => $customer->id, 'invoice_number' => 'INV-1', 'issued_on' => '2026-01-10',
            'receivable_account_id' => $ar->id, 'revenue_account_id' => $revenue->id,
            'idempotency_key' => 'invoice-1', 'lines' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '1000000']],
        ]);

        $service->receipt([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'invoice_id' => $invoice->id, 'receipt_number' => 'REC-1', 'received_on' => '2026-01-15',
            'amount' => '400000', 'cash_account_id' => $cash->id, 'receivable_account_id' => $ar->id,
            'idempotency_key' => 'receipt-1',
        ]);

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertEquals(400000, $invoice->fresh()->receipts->sum('amount'));
    }

    public function test_closed_period_rejects_invoice(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $period->update(['status' => 'closed']);

        $this->expectException(InvalidArgumentException::class);
        app(TransactionPostingService::class)->invoice([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'customer_id' => $company->customers()->create(['name' => 'Buyer', 'code' => 'BUY-2'])->id,
            'invoice_number' => 'INV-2', 'issued_on' => '2026-01-10',
            'receivable_account_id' => 1, 'revenue_account_id' => 1,
            'idempotency_key' => 'invoice-2', 'lines' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100']],
        ]);
    }

    public function test_invoice_and_receipt_are_company_scoped(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $other = Company::create(['name' => 'Other Co', 'code' => 'OTHER']);
        $customer = $company->customers()->create(['name' => 'Buyer', 'code' => 'BUY-3']);
        $ar = $company->accounts()->create(['code' => '1200', 'name' => 'AR', 'type' => 'asset']);
        $revenue = $company->accounts()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'income']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(TransactionPostingService::class)->invoice([
            'company_id' => $other->id,
            'branch_id' => $branch->id,
            'fiscal_period_id' => $period->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-3',
            'issued_on' => '2026-01-10',
            'receivable_account_id' => $ar->id,
            'revenue_account_id' => $revenue->id,
            'idempotency_key' => 'invoice-3',
            'lines' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100']],
        ]);
    }

    public function test_full_invoice_settlement_marks_invoice_paid(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $customer = $company->customers()->create(['name' => 'Buyer', 'code' => 'BUY-FULL']);
        $ar = $company->accounts()->create(['code' => '1200', 'name' => 'AR', 'type' => 'asset']);
        $revenue = $company->accounts()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'income']);
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $service = app(TransactionPostingService::class);

        $invoice = $service->invoice([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'customer_id' => $customer->id, 'invoice_number' => 'INV-FULL', 'issued_on' => '2026-01-10',
            'receivable_account_id' => $ar->id, 'revenue_account_id' => $revenue->id,
            'idempotency_key' => 'invoice-full', 'lines' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100']],
        ]);

        $service->settleInvoice([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'invoice_id' => $invoice->id, 'received_on' => '2026-01-15', 'amount' => '100',
            'cash_account_id' => $cash->id, 'receivable_account_id' => $ar->id, 'idempotency_key' => 'receipt-full',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_expense_payment_updates_partial_status_and_balances_journal(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $expenseAccount = $company->accounts()->create(['code' => '5200', 'name' => 'Expense', 'type' => 'expense']);
        $payable = $company->accounts()->create(['code' => '2100', 'name' => 'AP', 'type' => 'liability']);
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);

        $expense = app(TransactionPostingService::class)->expense([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'expense_date' => '2026-01-10', 'amount' => '100000',
            'expense_account_id' => $expenseAccount->id, 'payable_account_id' => $payable->id,
            'idempotency_key' => 'expense-ap-1',
        ]);

        app(TransactionPostingService::class)->expensePayment([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'expense_id' => $expense->id, 'paid_on' => '2026-01-15', 'amount' => '60000',
            'payable_account_id' => $payable->id, 'cash_account_id' => $cash->id,
            'idempotency_key' => 'expense-payment-1',
        ]);

        $this->assertSame('partial', $expense->fresh()->status);
        $this->assertSame('40000.00', number_format((float) $expense->fresh()->amount - (float) $expense->fresh()->payments->sum('amount'), 2, '.', ''));
        $this->assertSame('0.00', JournalEntry::query()->with('lines')->get()->sum(fn ($entry) => (float) $entry->lines->sum('debit') - (float) $entry->lines->sum('credit')) . '.00');
    }

    public function test_posted_journal_can_be_reversed_once(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $equity = $company->accounts()->create(['code' => '3100', 'name' => 'Equity', 'type' => 'equity']);
        $entry = app(\App\Services\Accounting\JournalPostingService::class)->post([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'posted_on' => '2026-01-01', 'description' => 'Opening', 'idempotency_key' => 'opening-1',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => '100', 'credit' => '0'],
                ['account_id' => $equity->id, 'debit' => '0', 'credit' => '100'],
            ],
        ]);

        $reversal = app(\App\Services\Accounting\JournalReversalService::class)->reverse($entry, [
            'idempotency_key' => 'reversal-1', 'posted_on' => '2026-01-02',
        ]);

        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_expense_payment_updates_status_and_posts_to_ledger(): void
    {
        [$company, $branch, $period] = $this->foundation();
        $expenseAccount = $company->accounts()->create(['code' => '5100', 'name' => 'Expense', 'type' => 'expense']);
        $payable = $company->accounts()->create(['code' => '2100', 'name' => 'AP', 'type' => 'liability']);
        $cash = $company->accounts()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $expense = app(TransactionPostingService::class)->expense([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'expense_account_id' => $expenseAccount->id, 'payable_account_id' => $payable->id,
            'expense_date' => '2026-01-10', 'amount' => '100000', 'idempotency_key' => 'expense-1',
        ]);

        app(TransactionPostingService::class)->expensePayment([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'fiscal_period_id' => $period->id,
            'expense_id' => $expense->id, 'cash_account_id' => $cash->id, 'payable_account_id' => $payable->id,
            'paid_on' => '2026-01-15', 'amount' => '60000', 'idempotency_key' => 'payment-1',
        ]);

        $this->assertSame('partial', $expense->fresh()->status);
        $this->assertCount(2, JournalEntry::where('company_id', $company->id)->get());
    }

    private function foundation(): array
    {
        $company = Company::create(['name' => 'Test Co', 'code' => 'TEST']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $period = $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $user = User::factory()->create();
        $this->actingAs($user);

        return [$company, $branch, $period];
    }
}
