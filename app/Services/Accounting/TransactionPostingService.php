<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
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
            $this->post([...$data, 'source_kind' => 'expense_payment'], $payment->id, [
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
            $this->post([...$data, 'source_kind' => 'expense', 'reference' => $expense->reference], $expense->id, [
                ['account_id' => $data['expense_account_id'], 'debit' => $data['amount'], 'credit' => '0'],
                ['account_id' => $data['payment_account_id'] ?? $data['payable_account_id'], 'debit' => '0', 'credit' => $data['amount']],
            ]);
            return $expense->load('payments');
        });
    }

    /**
     * Next automatic invoice number for the issue year (INV-YYYY-00001). Unique per company;
     * the posting transaction re-reads it under the unique (company_id, invoice_number) index.
     */
    public function nextInvoiceNumber(int $companyId, string $issuedOn): string
    {
        $prefix = 'INV-'.substr($issuedOn, 0, 4).'-';
        $last = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Post a sales invoice (PRD 03 #1). Money is bcmath strings throughout.
     *
     * Lines:  quantity x unit price, discount %, own revenue group ("kelompok pendapatan").
     * Extras: up to 3 "pendapatan lain", PPN on the DPP (after discount), PPh23 estimate (ADR-008),
     *         an initial payment (cash/bank) and the remainder as receivable.
     *
     * With a discount account the books show gross revenue and the discount as an expense
     * (the "Beban Discount" column of the reference); without one, revenue is booked net.
     *
     *   Dr Cash/Bank (paid) + Dr Receivable (rest) + Dr Discount expense
     *   Cr Revenue per group + Cr Other income + Cr VAT output
     */
    public function invoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $companyId = $data['company_id'];
            if (FiscalPeriod::findOrFail($data['fiscal_period_id'])->status !== 'open') {
                throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
            }
            Customer::where('company_id', $companyId)->findOrFail($data['customer_id']);
            $this->unique(Invoice::class, $data);

            $lines = array_values($data['lines']);
            $otherIncomes = array_values($data['other_incomes'] ?? []);
            if (count($lines) < 1 || count($lines) > 15) {
                throw new InvalidArgumentException('An invoice needs 1 to 15 product lines.');
            }
            if (count($otherIncomes) > 3) {
                throw new InvalidArgumentException('An invoice allows at most 3 other income rows.');
            }

            $defaultRevenueId = $data['revenue_account_id'] ?? $lines[0]['revenue_account_id'] ?? null;
            if (! $defaultRevenueId) {
                throw new InvalidArgumentException('Invoice requires a revenue account.');
            }
            $receivable = $this->account($companyId, $data['receivable_account_id'], 'asset');
            $discountAccount = ! empty($data['discount_account_id']) ? $this->account($companyId, $data['discount_account_id'], 'expense') : null;
            $vatEnabled = (bool) ($data['vat_enabled'] ?? false);
            $vatAccount = $vatEnabled ? $this->account($companyId, $data['vat_output_account_id'], 'liability') : null;

            // Revenue credits aggregated per account (one journal line per account).
            $revenue = [];
            $credit = function (int $accountId, string $amount) use (&$revenue) {
                $revenue[$accountId] = bcadd($revenue[$accountId] ?? '0.00', $amount, 2);
            };

            $discountTotal = '0.00';
            $dpp = '0.00';
            $lineRows = [];
            foreach ($lines as $line) {
                $revenueId = (int) ($line['revenue_account_id'] ?? $defaultRevenueId);
                $this->account($companyId, $revenueId, 'income');
                $gross = bcmul((string) $line['quantity'], (string) $line['unit_price'], 2);
                $discount = bcdiv(bcmul($gross, (string) ($line['discount_percent'] ?? '0'), 4), '100', 2);
                $net = bcsub($gross, $discount, 2);
                $discountTotal = bcadd($discountTotal, $discount, 2);
                $dpp = bcadd($dpp, $net, 2);
                $credit($revenueId, $discountAccount ? $gross : $net);
                $lineRows[] = [
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'] ?? '0',
                    'line_total' => $net,
                    'product_id' => $line['product_id'] ?? null,
                    'revenue_account_id' => $revenueId,
                ];
            }

            $otherTotal = '0.00';
            foreach ($otherIncomes as $other) {
                if (bccomp((string) $other['amount'], '0', 2) <= 0) {
                    throw new InvalidArgumentException('Other income amount must be positive.');
                }
                $this->account($companyId, $other['account_id'], 'income');
                $amount = bcadd((string) $other['amount'], '0', 2);
                $otherTotal = bcadd($otherTotal, $amount, 2);
                $credit((int) $other['account_id'], $amount);
            }
            $dpp = bcadd($dpp, $otherTotal, 2);

            $vatRate = $vatEnabled ? (string) $data['vat_rate'] : '0';
            $vatAmount = bcdiv(bcmul($dpp, $vatRate, 4), '100', 2);
            $pph23Rate = (string) ($data['pph23_rate'] ?? '0');
            $pph23Amount = bcdiv(bcmul($dpp, $pph23Rate, 4), '100', 2);
            $total = bcadd($dpp, $vatAmount, 2);
            if (bccomp($total, '0', 2) <= 0) {
                throw new InvalidArgumentException('Invoice total must be positive.');
            }

            $paid = bcadd((string) ($data['paid_amount'] ?? '0'), '0', 2);
            if (bccomp($paid, '0', 2) < 0 || bccomp($paid, $total, 2) > 0) {
                throw new InvalidArgumentException('Paid amount must be between 0 and the invoice total.');
            }
            $paymentAccount = null;
            if (bccomp($paid, '0', 2) > 0) {
                if (empty($data['payment_account_id'])) {
                    throw new InvalidArgumentException('A payment account is required when an amount is paid.');
                }
                $paymentAccount = $this->account($companyId, $data['payment_account_id'], 'asset');
                if ($paymentAccount->id === $receivable->id) {
                    throw new InvalidArgumentException('Payment account must differ from the receivable account.');
                }
            }
            $remaining = bcsub($total, $paid, 2);

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'customer_id' => $data['customer_id'],
                'invoice_number' => ($data['invoice_number'] ?? null) ?: $this->nextInvoiceNumber($companyId, (string) $data['issued_on']),
                'receivable_account_id' => $receivable->id,
                'revenue_account_id' => $defaultRevenueId,
                'vat_output_account_id' => $vatAccount?->id,
                'discount_account_id' => $discountAccount?->id,
                'payment_account_id' => $paymentAccount?->id,
                'payment_method' => $data['payment_method'] ?? null,
                'issued_on' => $data['issued_on'],
                'idempotency_key' => $data['idempotency_key'],
                'total' => $total,
                'dpp' => $dpp,
                'discount_total' => $discountTotal,
                'other_income_total' => $otherTotal,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'pph23_rate' => $pph23Rate,
                'pph23_amount' => $pph23Amount,
                'paid_amount' => $paid,
                'status' => bccomp($remaining, '0', 2) === 0 ? 'paid' : (bccomp($paid, '0', 2) > 0 ? 'partial' : 'unpaid'),
            ]);
            $invoice->lines()->createMany($lineRows);
            foreach ($otherIncomes as $other) {
                $invoice->otherIncomes()->create(['label' => $other['label'], 'account_id' => $other['account_id'], 'amount' => $other['amount']]);
            }

            $journalLines = [];
            if ($paymentAccount) {
                $journalLines[] = ['account_id' => $paymentAccount->id, 'debit' => $paid, 'credit' => '0'];
            }
            if (bccomp($remaining, '0', 2) > 0) {
                $journalLines[] = ['account_id' => $receivable->id, 'debit' => $remaining, 'credit' => '0'];
            }
            if ($discountAccount && bccomp($discountTotal, '0', 2) > 0) {
                $journalLines[] = ['account_id' => $discountAccount->id, 'debit' => $discountTotal, 'credit' => '0'];
            }
            foreach ($revenue as $accountId => $amount) {
                if (bccomp($amount, '0', 2) > 0) {
                    $journalLines[] = ['account_id' => $accountId, 'debit' => '0', 'credit' => $amount];
                }
            }
            if ($vatAccount && bccomp($vatAmount, '0', 2) > 0) {
                $journalLines[] = ['account_id' => $vatAccount->id, 'debit' => '0', 'credit' => $vatAmount];
            }

            $entry = $this->post([...$data, 'source_kind' => 'invoice', 'reference' => $invoice->invoice_number], $invoice->id, $journalLines);
            $invoice->update(['journal_entry_id' => $entry->id]);

            // The payment taken at issue is a real receipt so the AR subledger and the GL agree.
            if ($paymentAccount) {
                $receipt = Receipt::create([
                    'company_id' => $companyId,
                    'branch_id' => $data['branch_id'],
                    'fiscal_period_id' => $data['fiscal_period_id'],
                    'receipt_number' => 'RCP-'.$invoice->invoice_number,
                    'received_on' => $data['issued_on'],
                    'invoice_id' => $invoice->id,
                    'cash_account_id' => $paymentAccount->id,
                    'receivable_account_id' => $receivable->id,
                    'amount' => $paid,
                    'idempotency_key' => $data['idempotency_key'].':initial-payment',
                    'journal_entry_id' => $entry->id,
                ]);
                $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $paid]);
            }

            return $invoice->load('lines', 'otherIncomes');
        });
    }

    /**
     * Correct a posted invoice by reversing it (never delete or edit the journal). The cash taken at
     * issue is reversed with it; any later receipt must be cancelled first.
     *
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverseInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'reversed') {
                throw new InvalidArgumentException('Invoice sudah dibalik.');
            }

            $laterReceipt = $invoice->receiptAllocations()->whereHas('receipt', fn ($q) => $q
                ->whereNull('journal_entry_id')->orWhere('journal_entry_id', '!=', $invoice->journal_entry_id))->exists();
            if ($laterReceipt) {
                throw new InvalidArgumentException('Invoice sudah memiliki pelunasan susulan. Batalkan pelunasan terlebih dahulu.');
            }

            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $invoice->issued_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal invoice.');
            }
            $period = FiscalPeriod::where('company_id', $data['company_id'])->where('status', 'open')
                ->whereDate('starts_on', '<=', $postedOn)->whereDate('ends_on', '>=', $postedOn)->first();
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }

            $reversal = app(JournalReversalService::class)->reverse(
                JournalEntry::where('company_id', $data['company_id'])->findOrFail($invoice->journal_entry_id),
                [
                    'posted_on' => $postedOn,
                    'fiscal_period_id' => $period->id,
                    'description' => 'Pembalikan invoice '.$invoice->invoice_number.': '.$data['reason'],
                    'idempotency_key' => 'invoice-reversal-'.$invoice->id,
                    'created_by' => $data['created_by'] ?? null,
                ],
            );

            $invoice->update([
                'status' => 'reversed',
                'reversal_journal_entry_id' => $reversal->id,
                'reversal_reason' => $data['reason'],
                'reversed_at' => now(),
            ]);

            return $invoice->fresh();
        });
    }

    /** Company-scoped account lookup that also enforces the account's type. */
    private function account(int $companyId, int|string $id, string $type): Account
    {
        $account = Account::where('company_id', $companyId)->findOrFail($id);
        if ($account->type !== $type) {
            throw new InvalidArgumentException("Account {$account->code} must be of type {$type}.");
        }

        return $account;
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
            $this->post([...$data, 'source_kind' => 'receipt', 'reference' => $receipt->receipt_number], $receipt->id, [
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
            $this->post([...$data, 'source_kind' => 'expense_payment'], $payment->id, [
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

    private function post(array $data, int $sourceId, array $lines): JournalEntry
    {
        return app(JournalPostingService::class)->post([
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
