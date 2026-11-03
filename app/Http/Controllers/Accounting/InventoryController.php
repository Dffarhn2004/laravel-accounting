<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Services\Accounting\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryReportService $report): Response
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->where('company_id', $companyId)],
        ]);
        $filters = [
            'year' => (int) ($data['year'] ?? now()->year),
            'month' => (int) ($data['month'] ?? now()->month),
            'branch_id' => $request->user()->branch_id ? (int) $request->user()->branch_id : (isset($data['branch_id']) ? (int) $data['branch_id'] : null),
            'item_id' => isset($data['item_id']) ? (int) $data['item_id'] : null,
        ];
        $built = $report->build($filters + ['company_id' => $companyId]);

        return Inertia::render('accounting/inventory', [
            'rows' => $built['rows'],
            'card' => $built['card'],
            'reconciliation' => $built['reconciliation'],
            'asOf' => $built['as_of'],
            'filters' => $filters,
            'branches' => Branch::where('company_id', $companyId)->when($request->user()->branch_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'code', 'name']),
            'items' => InventoryItem::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }
}
