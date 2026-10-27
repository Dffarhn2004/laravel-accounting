<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalLine;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $lines = JournalLine::query()
            ->with(['account', 'journalEntry.branch'])
            ->whereHas('journalEntry', function ($query) use ($request, $user) {
                $query->when($user?->company_id, fn ($entry, $id) => $entry->where('company_id', $id))
                    ->when($user?->branch_id, fn ($entry, $id) => $entry->where('branch_id', $id))
                    ->when($request->date('from'), fn ($entry, $date) => $entry->whereDate('posted_on', '>=', $date))
                    ->when($request->date('to'), fn ($entry, $date) => $entry->whereDate('posted_on', '<=', $date));
            })
            ->when($request->integer('account_id'), fn ($query, $id) => $query->where('account_id', $id))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('accounting/ledger', [
            'lines' => $lines,
            'accounts' => Account::query()
                ->when($user?->company_id, fn ($query, $id) => $query->where('company_id', $id))
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => $request->only(['account_id', 'from', 'to']),
        ]);
    }
}
