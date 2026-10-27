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
        $asOf = $filters['as_of'] ?? now()->toDateString();

        return Invoice::query()
            ->with('customer')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('issued_on', '<=', $asOf)
            ->get()
            ->map(fn (Invoice $invoice) => $this->row(
                $invoice->invoice_number,
                $invoice->customer?->name,
                $invoice->issued_on,
                $invoice->total,
                $invoice->receiptAllocations()->whereDate('created_at', '<=', $asOf)->sum('amount'),
                $asOf,
            ))
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();
    }

    public function payables(array $filters = []): Collection
    {
        $asOf = $filters['as_of'] ?? now()->toDateString();

        return Expense::query()
            ->with('vendor')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('expense_date', '<=', $asOf)
            ->get()
            ->map(fn (Expense $expense) => $this->row(
                $expense->reference ?? 'EXP-'.$expense->id,
                $expense->vendor?->name,
                $expense->expense_date,
                $expense->amount,
                $expense->payments()->whereDate('created_at', '<=', $asOf)->sum('amount'),
                $asOf,
            ))
            ->filter(fn (array $row) => $row['outstanding'] > 0)
            ->values();
    }

    private function row(string $number, ?string $party, $date, $total, $paid, string $asOf): array
    {
        $outstanding = round((float) $total - (float) $paid, 2);
        $days = max(0, Carbon::parse($date)->diffInDays(Carbon::parse($asOf)));

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
