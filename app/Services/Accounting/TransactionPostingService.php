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
use App\Models\ReceiptAllocation;
use App\Models\TemplateOption;
use App\Support\Accounting\Periods;
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
            $paid = $this->settledAmount($expense);
            $remaining = bcsub((string) $expense->amount, $paid, 2);
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

    /** Next automatic reference for the expense year (BBN-YYYY-00001), shown as "No Trx" in the journal. */
    public function nextExpenseReference(int $companyId, string $expenseDate): string
    {
        $prefix = 'BBN-'.substr($expenseDate, 0, 4).'-';
        $last = Expense::where('company_id', $companyId)->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Post an operating expense (PRD 03 #3). Dr expense account / Cr cash-bank (paid) or Cr payable (open).
     * Accounts are company-scoped and type-checked: the expense account must be an expense (5xxx),
     * a payment account an asset, a payable account a liability.
     */
    public function expense(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $companyId = $data['company_id'];
            if (FiscalPeriod::findOrFail($data['fiscal_period_id'])->status !== 'open') {
                throw new InvalidArgumentException('Cannot post to a closed fiscal period.');
            }
            $this->unique(Expense::class, $data);
            if (empty($data['payment_account_id']) && empty($data['payable_account_id'])) {
                throw new InvalidArgumentException('Expense requires a payment or payable account.');
            }
            if (bccomp((string) $data['amount'], '0', 2) <= 0) {
                throw new InvalidArgumentException('Expense amount must be positive.');
            }

            $expenseAccount = $this->account($companyId, $data['expense_account_id'], 'expense');
            $paid = ! empty($data['payment_account_id']);
            $settlement = $paid
                ? $this->account($companyId, $data['payment_account_id'], 'asset')
                : $this->account($companyId, $data['payable_account_id'], 'liability');
            $amount = bcadd((string) $data['amount'], '0', 2);
            $reference = ($data['reference'] ?? null) ?: $this->nextExpenseReference($companyId, (string) $data['expense_date']);

            $expense = Expense::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'fiscal_period_id' => $data['fiscal_period_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'expense_account_id' => $expenseAccount->id,
                'settlement_account_id' => $settlement->id,
                'expense_date' => $data['expense_date'],
                'reference' => $reference,
                'description' => $data['description'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'amount' => $amount,
                'status' => $paid ? 'paid' : 'open',
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $entry = $this->post([...$data, 'description' => ($data['description'] ?? null) ?: 'Beban '.$expenseAccount->name, 'source_kind' => 'expense', 'reference' => $reference], $expense->id, [
                ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => '0'],
                ['account_id' => $settlement->id, 'debit' => '0', 'credit' => $amount],
            ]);
            $expense->update(['journal_entry_id' => $entry->id]);

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
                if (array_key_exists('discount_amount', $line) && $line['discount_amount'] !== null && $line['discount_amount'] !== '') {
                    $discount = bcadd((string) $line['discount_amount'], '0', 2);
                    if (bccomp($discount, '0', 2) < 0 || bccomp($discount, $gross, 2) > 0) {
                        throw new InvalidArgumentException('Line discount must be between 0 and the line gross.');
                    }
                    $percent = bccomp($gross, '0', 2) > 0 ? bcdiv(bcmul($discount, '100', 4), $gross, 2) : '0.00';
                } else {
                    $percent = (string) ($line['discount_percent'] ?? '0');
                    $discount = bcdiv(bcmul($gross, $percent, 4), '100', 2);
                }
                $net = bcsub($gross, $discount, 2);
                $discountTotal = bcadd($discountTotal, $discount, 2);
                $dpp = bcadd($dpp, $net, 2);
                $credit($revenueId, $discountAccount ? $gross : $net);
                $lineRows[] = [
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $percent,
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

            if ($vatEnabled && array_key_exists('vat_amount', $data) && $data['vat_amount'] !== null && $data['vat_amount'] !== '') {
                $vatAmount = bcadd((string) $data['vat_amount'], '0', 2);
                if (bccomp($vatAmount, '0', 2) < 0) {
                    throw new InvalidArgumentException('VAT amount cannot be negative.');
                }
                $vatRate = bccomp($dpp, '0', 2) > 0 ? bcdiv(bcmul($vatAmount, '100', 4), $dpp, 2) : '0.00';
            } else {
                $vatRate = $vatEnabled ? (string) $data['vat_rate'] : '0';
                $vatAmount = bcdiv(bcmul($dpp, $vatRate, 4), '100', 2);
            }
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
                ->whereNull('reversed_at')
                ->where(fn ($q2) => $q2->whereNull('journal_entry_id')->orWhere('journal_entry_id', '!=', $invoice->journal_entry_id)))->exists();
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
            $paid = $this->liveAllocated($invoice);
            $remaining = bcsub((string) $invoice->total, $paid, 2);
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

    /**
     * Settle one open invoice (PRD 03 #5). Branch and the receivable account come from the invoice.
     * Cash is the template payment method. Optional PPh23 (ADR-008) is booked only when the customer
     * withholds: Dr cash (net) + Dr 1404, Cr receivable (gross).
     *
     * @param  array{company_id:int, invoice_id:int, received_on:string, payment_method:string, amount:string, pph23_amount?:string, description:string, idempotency_key:string, created_by?:int}  $data
     */
    public function collectReceivable(array $data): Receipt
    {
        return DB::transaction(function () use ($data) {
            $companyId = (int) $data['company_id'];
            $invoice = Invoice::where('company_id', $companyId)->lockForUpdate()->findOrFail($data['invoice_id']);
            if ($invoice->status === 'reversed') {
                throw new InvalidArgumentException('Invoice sudah dibalik.');
            }
            if (! $invoice->receivable_account_id) {
                throw new InvalidArgumentException('Invoice ini tidak memiliki akun piutang.');
            }

            $receivedOn = (string) $data['received_on'];
            if ($receivedOn < $invoice->issued_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal bayar tidak boleh sebelum tanggal invoice.');
            }
            $period = Periods::openFor($companyId, $receivedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal ini.');
            }
            if (Receipt::where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw new InvalidArgumentException('Pelunasan ini sudah diposting.');
            }

            $option = TemplateOption::where([
                'company_id' => $companyId,
                'group' => 'invoice_payment',
                'code' => $data['payment_method'],
                'is_active' => true,
            ])->first();
            $cash = $option?->account_id
                ? Account::where('company_id', $companyId)->where('is_active', true)->find($option->account_id)
                : null;
            if (! $cash || $cash->type !== 'asset') {
                throw new InvalidArgumentException('Jenis pembayaran harus kas atau bank. Atur di Template Jurnal.');
            }
            $receivable = $this->account($companyId, $invoice->receivable_account_id, 'asset');
            if ($cash->id === $receivable->id) {
                throw new InvalidArgumentException('Akun kas tidak boleh sama dengan akun piutang.');
            }

            $cashAmount = bcadd((string) $data['amount'], '0', 2);
            $withheld = bcadd((string) ($data['pph23_amount'] ?? '0'), '0', 2);
            if (bccomp($cashAmount, '0', 2) <= 0) {
                throw new InvalidArgumentException('Jumlah dibayar harus lebih dari nol.');
            }
            if (bccomp($withheld, '0', 2) < 0) {
                throw new InvalidArgumentException('PPh23 dipotong tidak boleh negatif.');
            }
            $gross = bcadd($cashAmount, $withheld, 2);
            $paid = $this->liveAllocated($invoice);
            $remaining = bcsub((string) $invoice->total, $paid, 2);
            if (bccomp($remaining, '0', 2) <= 0) {
                throw new InvalidArgumentException('Invoice ini sudah lunas.');
            }
            if (bccomp($gross, $remaining, 2) > 0) {
                throw new InvalidArgumentException('Pembayaran melebihi sisa piutang.');
            }
            $withheldSoFar = LedgerService::money(
                Receipt::where('invoice_id', $invoice->id)->whereNull('reversed_at')->sum('pph23_amount')
            );
            $estimateLeft = bcsub((string) $invoice->pph23_amount, $withheldSoFar, 2);
            if (bccomp($withheld, $estimateLeft, 2) > 0) {
                throw new InvalidArgumentException('PPh23 dipotong melebihi estimasi yang belum dibukukan.');
            }

            $lines = [['account_id' => $cash->id, 'debit' => $cashAmount, 'credit' => '0']];
            if (bccomp($withheld, '0', 2) > 0) {
                $prepaid = Account::where('company_id', $companyId)->where('code', '1404')->where('is_active', true)->first();
                if (! $prepaid) {
                    throw new InvalidArgumentException('Akun 1404 PPh23 Dibayar di Muka tidak ditemukan.');
                }
                $lines[] = ['account_id' => $prepaid->id, 'debit' => $withheld, 'credit' => '0'];
            }
            $lines[] = ['account_id' => $receivable->id, 'debit' => '0', 'credit' => $gross];

            $number = $this->nextReceiptNumber($companyId, $receivedOn);
            $receipt = Receipt::create([
                'company_id' => $companyId,
                'branch_id' => $invoice->branch_id,
                'fiscal_period_id' => $period->id,
                'receipt_number' => $number,
                'received_on' => $receivedOn,
                'invoice_id' => $invoice->id,
                'cash_account_id' => $cash->id,
                'receivable_account_id' => $receivable->id,
                'amount' => $cashAmount,
                'pph23_amount' => $withheld,
                'description' => $data['description'],
                'payment_method' => $data['payment_method'],
                'status' => 'posted',
                'idempotency_key' => $data['idempotency_key'],
            ]);
            $entry = $this->post([
                'company_id' => $companyId,
                'branch_id' => $invoice->branch_id,
                'fiscal_period_id' => $period->id,
                'posted_on' => $receivedOn,
                'description' => $data['description'],
                'idempotency_key' => $data['idempotency_key'],
                'source_kind' => 'receipt',
                'reference' => $number,
                'created_by' => $data['created_by'] ?? null,
            ], $receipt->id, $lines);
            $receipt->update(['journal_entry_id' => $entry->id]);
            $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $gross]);
            $this->refreshInvoiceStatus($invoice);

            return $receipt->fresh('allocations');
        });
    }

    /**
     * Cancel a later settlement. The invoice status returns to whatever is still unpaid,
     * so the invoice itself can then be reversed.
     *
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverseReceipt(Receipt $receipt, array $data): Receipt
    {
        return DB::transaction(function () use ($receipt, $data) {
            $receipt = Receipt::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status === 'reversed' || $receipt->reversed_at) {
                throw new InvalidArgumentException('Pelunasan ini sudah dibalik.');
            }
            if (! $receipt->journal_entry_id || ! str_starts_with((string) $receipt->receipt_number, 'PLT-')) {
                throw new InvalidArgumentException('Pembayaran awal pada invoice dibalik bersama invoice, bukan dari menu ini.');
            }

            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $receipt->received_on->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal bayar.');
            }
            $period = Periods::openFor((int) $data['company_id'], $postedOn);
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }

            $reversal = app(JournalReversalService::class)->reverse(
                JournalEntry::where('company_id', $data['company_id'])->findOrFail($receipt->journal_entry_id),
                [
                    'posted_on' => $postedOn,
                    'fiscal_period_id' => $period->id,
                    'description' => 'Pembalikan pelunasan '.$receipt->receipt_number.': '.$data['reason'],
                    'idempotency_key' => 'receipt-reversal-'.$receipt->id,
                    'created_by' => $data['created_by'] ?? null,
                ],
            );
            $receipt->update([
                'status' => 'reversed',
                'reversal_journal_entry_id' => $reversal->id,
                'reversal_reason' => $data['reason'],
                'reversed_at' => now(),
            ]);
            $invoice = Invoice::where('company_id', $data['company_id'])->lockForUpdate()->find($receipt->invoice_id);
            if ($invoice && $invoice->status !== 'reversed') {
                $this->refreshInvoiceStatus($invoice);
            }

            return $receipt->fresh();
        });
    }

    /** Allocations whose receipt has not been reversed, including the payment taken when the invoice was issued. */
    private function liveAllocated(Invoice $invoice): string
    {
        return LedgerService::money(
            ReceiptAllocation::where('invoice_id', $invoice->id)
                ->whereHas('receipt', fn ($q) => $q->whereNull('reversed_at'))
                ->sum('amount')
        );
    }

    private function refreshInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'reversed') {
            return;
        }
        $paid = $this->liveAllocated($invoice);
        $status = match (true) {
            bccomp($paid, '0', 2) === 0 => 'unpaid',
            bccomp($paid, (string) $invoice->total, 2) >= 0 => 'paid',
            default => 'partial',
        };
        $invoice->update(['status' => $status]);
    }

    private function nextReceiptNumber(int $companyId, string $receivedOn): string
    {
        $prefix = 'PLT-'.substr($receivedOn, 0, 4).'-';
        $last = Receipt::where('company_id', $companyId)->where('receipt_number', 'like', $prefix.'%')->orderByDesc('receipt_number')->value('receipt_number');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix)) + 1) : 1), 5, '0', STR_PAD_LEFT);
    }

    public function expensePayment(array $data): ExpensePayment
    {
        return DB::transaction(function () use ($data) {
            $this->scope($data);
            $expense = Expense::query()
                ->where('company_id', $data['company_id'])
                ->lockForUpdate()
                ->findOrFail($data['expense_id']);
            $paid = $this->settledAmount($expense);
            $remaining = bcsub((string) $expense->amount, $paid, 2);
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

    /**
     * Correct a posted expense by reversing it (the journal is never edited or deleted).
     * Refused when part of it has already been paid: cancel those payments first.
     *
     * @param  array{company_id:int, reason:string, posted_on?:string, created_by?:int}  $data
     */
    public function reverseExpense(int|Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $id = $expense instanceof Expense ? $expense->id : $expense;
            $expense = Expense::where('company_id', $data['company_id'])->lockForUpdate()->findOrFail($id);

            if ($expense->status === 'reversed') {
                throw new InvalidArgumentException('Beban sudah dibalik.');
            }
            if ($expense->payments()->whereNull('reversed_at')->exists()) {
                throw new InvalidArgumentException('Beban sudah memiliki pelunasan hutang. Batalkan pelunasan terlebih dahulu.');
            }

            $postedOn = $data['posted_on'] ?? now()->toDateString();
            if ($postedOn < $expense->expense_date->toDateString()) {
                throw new InvalidArgumentException('Tanggal pembalikan tidak boleh sebelum tanggal beban.');
            }
            $period = FiscalPeriod::where('company_id', $data['company_id'])->where('status', 'open')
                ->whereDate('starts_on', '<=', $postedOn)->whereDate('ends_on', '>=', $postedOn)->first();
            if (! $period) {
                throw new InvalidArgumentException('Tidak ada tahun buku terbuka untuk tanggal pembalikan.');
            }

            $reversal = app(JournalReversalService::class)->reverse(
                JournalEntry::where('company_id', $data['company_id'])->findOrFail($expense->journal_entry_id),
                [
                    'posted_on' => $postedOn,
                    'fiscal_period_id' => $period->id,
                    'description' => 'Pembalikan beban '.$expense->reference.': '.$data['reason'],
                    'idempotency_key' => 'expense-reversal-'.$expense->id,
                    'created_by' => $data['created_by'] ?? null,
                ],
            );

            $expense->update(['status' => 'reversed', 'reversal_journal_entry_id' => $reversal->id, 'reversal_reason' => $data['reason'], 'reversed_at' => now()]);

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

    /** Live settlements only. A reversed cash/bank payment must not keep the expense paid. */
    private function settledAmount(Expense $expense): string
    {
        return LedgerService::money($expense->payments()->whereNull('reversed_at')->sum('amount'));
    }

    private function unique(string $model, array $data): void
    {
        if ($model::where('company_id', $data['company_id'])->where('idempotency_key', $data['idempotency_key'])->exists()) {
            throw new InvalidArgumentException('Duplicate idempotency key.');
        }
    }
}
