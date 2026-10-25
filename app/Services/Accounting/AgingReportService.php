<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class AgingReportService
{
    public function receivables(array $filters = []): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('issued_on', '<=', $filters['as_of'] ?? now()->toDateString())
            ->get()
            ->map(fn (Invoice $invoice) => $this->row(
                $invoice->invoice_number,
                $invoice->customer?->name,
                $invoice->issued_on,
                $invoice->total,
                $invoice->receiptAllocations()->whereDate('created_at', '<=', $filters['as_of'] ?? now()->toDateString())->sum('amount'),
            ))
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();
    }

    public function payables(array $filters = []): Collection
    {
        return Expense::query()
            ->with('vendor')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('expense_date', '<=', $filters['as_of'] ?? now()->toDateString())
            ->get()
            ->map(fn (Expense $expense) => $this->row(
                $expense->reference ?? 'EXP-'.$expense->id,
                $expense->vendor?->name,
                $expense->expense_date,
                $expense->amount,
                $expense->payments()->whereDate('created_at', '<=', $filters['as_of'] ?? now()->toDateString())->sum('amount'),
            ))
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();
    }

    private function row(string $number, ?string $party, $date, $total, $paid): array
    {
        $outstanding = round((float) $total - (float) $paid, 2);
        $days = max(0, Carbon::parse($date)->diffInDays(now()->startOfDay()));

        return [
            'number' => $number,
            'party' => $party,
            'date' => $date?->toDateString(),
            'total' => (float) $total,
            'paid' => (float) $paid,
            'outstanding' => $outstanding,
            'bucket' => match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            },
        ];
    }
}
