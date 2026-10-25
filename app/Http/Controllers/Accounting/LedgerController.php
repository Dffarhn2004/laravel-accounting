<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $lines = JournalLine::query()
            ->with(['account', 'journalEntry.branch'])
            ->whereHas('journalEntry', function ($query) use ($request, $companyId) {
                $query->when($user?->company_id, fn ($entry, $id) => $entry->where('company_id', $id))
                    ->when($user?->branch_id, fn ($entry, $id) => $entry->where('branch_id', $id))
                    ->when($request->date('from'), fn ($entry, $date) => $entry->whereDate('posted_on', '>=', $date))
                    ->when($request->date('to'), fn ($entry, $date) => $entry->whereDate('posted_on', '<=', $date));
            })
            ->when($request->integer('account_id'), fn ($query, $id) => $query->where('account_id', $id))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('accounting/ledger', ['lines' => $lines]);
    }
}
