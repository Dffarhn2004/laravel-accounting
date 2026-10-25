<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedAssetController extends Controller
{
    public function index(Request $request)
    {
        $assets = FixedAsset::query()
            ->with('schedule')
            ->where('company_id', $request->user()?->company_id)
            ->when($request->user()?->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->latest('acquired_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounting/fixed-assets', ['assets' => $assets]);
    }
}
