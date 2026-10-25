<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()?->company_id;

        return Inertia::render('accounting/inventory', [
            'items' => InventoryItem::query()
                ->when($companyId, fn ($query, $id) => $query->where('company_id', $id))
                ->with('costLayers')
                ->paginate(25),
            'movements' => InventoryMovement::query()
                ->when($companyId, fn ($query, $id) => $query->where('company_id', $id))
                ->with(['item', 'branch', 'journalEntry'])
                ->latest('moved_on')
                ->paginate(50, ['*'], 'movements_page'),
        ]);
    }
}
