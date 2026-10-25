<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Accounting\LedgerController;
use App\Http\Controllers\Accounting\TransactionController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Accounting\InventoryController;
use App\Http\Controllers\Accounting\ManualJournalController;
use App\Http\Controllers\Accounting\AgingController;
use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\MasterDataController;
use App\Http\Controllers\Accounting\TreasuryController;







Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('accounting/journal', [JournalController::class, 'index'])->name('accounting.journal');
    Route::get('accounting/ledger', [LedgerController::class, 'index'])->name('accounting.ledger');
    Route::get('accounting/transactions', [TransactionController::class, 'index'])->name('accounting.transactions');
    Route::post('accounting/expenses', [TransactionController::class, 'storeExpense'])->name('accounting.expenses.store');
    Route::post('accounting/invoices', [TransactionController::class, 'storeInvoice'])->name('accounting.invoices.store');
    Route::post('accounting/receipts', [TransactionController::class, 'storeReceipt'])->name('accounting.receipts.store');
    Route::get('accounting/treasury', [TreasuryController::class, 'index'])->name('accounting.treasury');
    Route::post('accounting/treasury/movements', [TreasuryController::class, 'movement'])->name('accounting.treasury.movement');
    Route::post('accounting/treasury/transfers', [TreasuryController::class, 'transfer'])->name('accounting.treasury.transfer');
    Route::get('accounting/reports', [ReportController::class, 'index'])->name('accounting.reports');
    Route::get('accounting/inventory', [InventoryController::class, 'index'])->name('accounting.inventory');
    Route::get('accounting/aging', [AgingController::class, 'index'])->name('accounting.aging');
    Route::get('accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
    Route::get('accounting/master-data', [MasterDataController::class, 'index'])->name('accounting.master-data');
    Route::post('accounting/master-data/branches', [MasterDataController::class, 'storeBranch'])->name('accounting.master-data.branches.store');
    Route::post('accounting/master-data/accounts', [MasterDataController::class, 'storeAccount'])->name('accounting.master-data.accounts.store');
    Route::post('accounting/master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('accounting.master-data.customers.store');
    Route::post('accounting/master-data/vendors', [MasterDataController::class, 'storeVendor'])->name('accounting.master-data.vendors.store');
    Route::post('accounting/master-data/branches/{branch}/deactivate', [MasterDataController::class, 'deactivateBranch'])->name('accounting.master-data.branches.deactivate');
    Route::post('accounting/master-data/accounts/{account}/deactivate', [MasterDataController::class, 'deactivateAccount'])->name('accounting.master-data.accounts.deactivate');
    Route::get('accounting/manual-journal', [ManualJournalController::class, 'create'])->name('accounting.manual-journal.create');
    Route::post('accounting/manual-journal', [ManualJournalController::class, 'store'])->name('accounting.manual-journal.store');

    Route::get('/modules/{section}', function (string $section) {
        abort_unless(in_array($section, [
            'master', 'transaksi', 'data', 'laporan', 'anggaran', 'dividen',
        ], true), 404);

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
    })->name('modules.show');

    Route::get('/accounting/journals', [JournalController::class, 'index'])
        ->name('accounting.journals.index');
});

require __DIR__.'/settings.php';
