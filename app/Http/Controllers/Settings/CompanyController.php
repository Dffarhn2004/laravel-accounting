<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('settings/company', ['company' => $request->user()->company]);
    }

    public function update(CompanyUpdateRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);
        $company->update($request->validated());

        return to_route('company.edit')->with('success', 'Pengaturan usaha berhasil disimpan.');
    }
}
