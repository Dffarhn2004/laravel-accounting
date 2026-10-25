<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionPostingService
{
    public function payExpense(array $data): ExpensePayment
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $expense = Expense::where('company_id', $data['company_id'])
                ->lockForUpdate()->findOrFail($data['expense_id']);
            $paid = $expense->payments()->sum('amount');
            $remaining = bcsub((string) $expense->amount, (string) $paid, 2);
            if (bccomp((string) $data['amount'], '0', 2) <= 0 ||
                bccomp((string) $data['amount'], $remaining, 2) > 0) {
                throw new InvalidArgumentException('Payment exceeds expense balance.');
            }
            $this->unique(ExpensePayment::class, $data);
            $payment = ExpensePayment::create([
                ...$data,
                'paid_on' => $data['paid_on'],
            ]);
            $payment->update(['journal_entry_id' => null]);
            $this->post($data, $payment->id, [
                ['account_id' => $data['payable_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['cash_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]);
            $newPaid = bcadd((string) $paid, (string) $data['amount'], 2);
            $expense->update(['status' => bccomp($newPaid, (string) $expense->amount, 2) === 0 ? 'paid' : 'partial']);
            return $payment;
        });
    }
    public function expense(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $this->unique(Expense::class, $data);
            if (! isset($data['payment_account_id']) && ! isset($data['payable_account_id'])) {
                throw new InvalidArgumentException('Expense requires a payment or payable account.');
            }
            $expense = Expense::create([
                ...collect($data)->except(['payment_account_id', 'payable_account_id'])->all(),
                'settlement_account_id' => $data['payment_account_id'] ?? $data['payable_account_id'],
                'status' => isset($data['payment_account_id']) ? 'paid' : 'open',
            ]);
            $this->post($data, $expense->id, [
                ['account_id' => $data['expense_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['payment_account_id'] ?? $data['payable_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]);
            return $expense->load('payments');
        });
    }

    public function invoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            if (FiscalPeriod::findOrFail($data['fiscal_period_id'])->status !== 'open') {
                throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
            }
            $this->unique(Invoice::class, $data);
            $total = collect($data['lines'])->reduce(
                fn (string $sum, array $line) => bcadd($sum, bcmul((string) $line['quantity'], (string) $line['unit_price'], 2), 2),
                '0.00',
            );
            $invoice = Invoice::create([
                ...collect($data)->except('lines')->all(),
                'total' => $total,
                'status' => 'unpaid',
            ]);
            $invoice->lines()->createMany($data['lines']);
            $this->post($data, $invoice->id, [
                ['account_id' => $data['receivable_account_id'], 'debit' => $total, 'credit' => '0'],
                ['account_id' => $data['revenue_account_id'], 'debit' => '0', 'credit' => $total],
            ]);
            return $invoice->load('lines');
        });
    }

    public function receipt(array $data): Receipt
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $invoice = Invoice::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($data['invoice_id']);
            $paid = $invoice->receiptAllocations()->sum('amount');
            $remaining = bcsub((string) $invoice->total, (string) $paid, 2);
            if (bccomp((string) $data['amount'], $remaining, 2) > 0) {
                throw new InvalidArgumentException('Receipt exceeds invoice balance.');
            }
            $this->unique(Receipt::class, $data);
            $receipt = Receipt::create(collect($data)->except('invoice_id')->all());
            $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $data['amount']]);
            $newPaid = bcadd((string) $paid, (string) $data['amount'], 2);
            $invoice->update(['status' => bccomp($newPaid, (string) $invoice->total, 2) === 0 ? 'paid' : 'partial']);
            $this->post($data, $receipt->id, [
                ['account_id' => $data['cash_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['receivable_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]);
            return $receipt;
        });
    }

    public function settleInvoice(array $data): Receipt
    {
        return $this->receipt($data);
    }

    public function expensePayment(array $data): ExpensePayment
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $expense = Expense::query()
                ->where('company_id', $data['company_id'])
                ->lockForUpdate()
                ->findOrFail($data['expense_id']);
            $paid = $expense->payments()->sum('amount');
            $remaining = bcsub((string) $expense->amount, (string) $paid, 2);
            if (bccomp((string) $data['amount'], $remaining, 2) > 0) {
                throw new InvalidArgumentException('Payment exceeds expense balance.');
            }
            $this->unique(ExpensePayment::class, $data);
            $payment = ExpensePayment::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'expense_id' => $expense->id,
                'cash_account_id' => $data['cash_account_id'],
                'payable_account_id' => $data['payable_account_id'],
                'paid_on' => $data['paid_on'],
                'amount' => $data['amount'],
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $newPaid = bcadd((string) $paid, (string) $data['amount'], 2);
            $expense->update(['status' => bccomp($newPaid, (string) $expense->amount, 2) === 0 ? 'paid' : 'partial']);
            $this->post($data, $payment->id, [
                ['account_id' => $data['payable_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['cash_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]);
            return $payment;
        });
    }

    public function reverseExpense(int $expenseId, array $data): Expense
    {
        return DB::transaction(function () use ($expenseId, $data) {
            $expense = Expense::where('company_id', $data['company_id'])
                ->with('journalEntry')
                ->findOrFail($expenseId);
            app(JournalReversalService::class)->reverse($expense->journalEntry, $data);
            $expense->update(['status' => 'reversed']);
            return $expense->fresh();
        });
    }

    public function reverse(JournalEntry $entry, int $userId): JournalEntry
    {
        return app(JournalReversalService::class)->reverse($entry, $userId);
    }

    private function post(array $data, int $sourceId, array $lines): void
    {
        app(JournalPostingService::class)->post([
            ...$data,
            'posted_on' => $data['posted_on'] ?? $data['expense_date'] ?? $data['issued_on'] ?? $data['received_on'] ?? $data['paid_on'],
            'description' => $data['description'] ?? 'Transaction',
            'idempotency_key' => $data['idempotency_key'],
            'source_type' => static::class,
            'source_id' => (string) $sourceId,
            'lines' => $lines,
        ]);
    }

    private function scope(array $data): void
    {
        FiscalPeriod::where('company_id', $data['company_id'])->findOrFail($data['fiscal_period_id']);
        Branch::where('company_id', $data['company_id'])->findOrFail($data['branch_id']);
    }

    private function unique(string $model, array $data): void
    {
        if ($model::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->exists()) {
            throw new InvalidArgumentException('Duplicate idempotency key.');
        }
    }
}
