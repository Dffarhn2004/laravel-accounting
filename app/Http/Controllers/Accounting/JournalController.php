<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $entries = JournalEntry::query()
            ->with(['branch', 'lines.account'])
            ->when($user?->company_id, fn ($query, $id) => $query->where('company_id', $id))
            ->when($user?->branch_id, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($request->date('from'), fn ($query, $date) => $query->whereDate('posted_on', '>=', $date))
            ->when($request->date('to'), fn ($query, $date) => $query->whereDate('posted_on', '<=', $date))
            ->latest('posted_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounting/journal', ['entries' => $entries]);
    }
}
