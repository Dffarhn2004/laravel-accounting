<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\DepreciationSimulationService;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class DepreciationSimulationController extends Controller
{
    public function index(Request $request, DepreciationSimulationService $simulation): Response
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        return Inertia::render('accounting/simulasi', [
            'filters' => ['year' => $year, 'month' => $month],
            'board' => $simulation->build($companyId, $request->user()->branch_id, $year, $month),
        ]);
    }

    public function post(Request $request, FixedAssetService $assets): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403, 'Akun Anda belum terhubung ke perusahaan.');
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $through = sprintf('%04d-%02d', $data['year'], $data['month']);

        try {
            $count = $assets->postThrough($companyId, $request->user()->branch_id, $through);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset' => $e->getMessage()]);
        }

        return back()->with('success', $count === 0 ? 'Tidak ada penyusutan yang jatuh tempo.' : $count.' bulan penyusutan diposting.');
    }
}
