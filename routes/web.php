<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Product-area placeholders only; accounting functionality is not implemented.
    Route::get('/modules/{section}', function (string $section) {
        abort_unless(in_array($section, [
            'master', 'transaksi', 'data', 'laporan', 'anggaran', 'dividen',
        ], true), 404);

        return Inertia::render('modules/show', ['section' => $section]);
    })->name('modules.show');
});

require __DIR__.'/settings.php';
