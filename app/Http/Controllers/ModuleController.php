<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function show(string $section): RedirectResponse|Response
    {
        abort_unless(in_array($section, ['master', 'transaksi', 'data', 'laporan', 'anggaran', 'dividen'], true), 404);

        $redirects = [
            'master' => 'accounting.master-data',
            'transaksi' => 'accounting.transactions',
            'data' => 'accounting.transactions',
            'laporan' => 'accounting.reports',
            'anggaran' => 'accounting.budgets',
        ];

        if (isset($redirects[$section])) {
            return to_route($redirects[$section]);
        }

        return Inertia::render('modules/show', ['section' => $section]);
    }
}
