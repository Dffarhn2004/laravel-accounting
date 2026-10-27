<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('settings/company', ['company' => $request->user()->company]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50', 'unique:companies,code,'.$company->id]]);
        $company->update($data);

        return back()->with('success', 'Company profile updated.');
    }
}
