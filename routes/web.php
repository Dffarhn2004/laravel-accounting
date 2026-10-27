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
use App\Http\Controllers\Accounting\OpeningBalanceController;
use App\Http\Controllers\Accounting\ClosingController;







Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('master/accounts', [MasterDataController::class, 'accounts'])->name('master.accounts');
    Route::get('master/branches', [MasterDataController::class, 'branches'])->name('master.branches');
    Route::get('master/customers', [MasterDataController::class, 'customers'])->name('master.customers');
    Route::get('master/vendors', [MasterDataController::class, 'vendors'])->name('master.vendors');
    Route::get('master/accounts-cash', [MasterDataController::class, 'cashAccounts'])->name('master.accounts-cash');
    Route::get('master/products', [MasterDataController::class, 'products'])->name('master.products');
    Route::get('transactions/invoices', [TransactionController::class, 'invoices'])->name('transactions.invoices.index');
    Route::get('transactions/invoices/create', [TransactionController::class, 'createInvoice'])->name('transactions.invoices.create');
    Route::get('transactions/invoices/{invoice}', [TransactionController::class, 'showInvoice'])->name('transactions.invoices.show');
    Route::get('transactions/expenses', [TransactionController::class, 'expenses'])->name('transactions.expenses.index');
    Route::get('transactions/expenses/create', [TransactionController::class, 'createExpense'])->name('transactions.expenses.create');
    Route::get('transactions/receivables', [TransactionController::class, 'receivables'])->name('transactions.receivables.index');
    Route::get('transactions/cash-bank', [TreasuryController::class, 'index'])->name('transactions.cash-bank.index');
    Route::get('transactions/fixed-assets', [FixedAssetController::class, 'index'])->name('transactions.fixed-assets.index');
    Route::post('transactions/fixed-assets', [FixedAssetController::class, 'store'])->name('transactions.fixed-assets.store');
    Route::post('transactions/fixed-assets/{asset}/depreciate', [FixedAssetController::class, 'depreciate'])->name('transactions.fixed-assets.depreciate');
    Route::get('accounting/journal', [JournalController::class, 'index'])->name('accounting.journal');
    Route::get('accounting/ledger', [LedgerController::class, 'index'])->name('accounting.ledger');
    Route::get('accounting/opening-balances', [OpeningBalanceController::class, 'create'])->name('accounting.opening-balances.create');
    Route::post('accounting/opening-balances', [OpeningBalanceController::class, 'store'])->name('accounting.opening-balances.store');
    Route::get('accounting/closing', [ClosingController::class, 'index'])->name('accounting.closing.index');
    Route::post('accounting/closing/{period}', [ClosingController::class, 'close'])->name('accounting.closing.close');
    Route::get('accounting/transactions', [TransactionController::class, 'index'])->name('accounting.transactions');
    Route::post('accounting/expenses', [TransactionController::class, 'storeExpense'])->name('accounting.expenses.store');
    Route::post('accounting/invoices', [TransactionController::class, 'storeInvoice'])->name('accounting.invoices.store');
    Route::post('accounting/receipts', [TransactionController::class, 'storeReceipt'])->name('accounting.receipts.store');
    Route::get('accounting/treasury', [TreasuryController::class, 'index'])->name('accounting.treasury');
    Route::post('accounting/treasury/movements', [TreasuryController::class, 'movement'])->name('accounting.treasury.movement');
    Route::post('accounting/treasury/transfers', [TreasuryController::class, 'transfer'])->name('accounting.treasury.transfer');
    Route::get('accounting/reports', [ReportController::class, 'index'])->name('accounting.reports');
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('accounting/inventory', [InventoryController::class, 'index'])->name('accounting.inventory');
    Route::get('accounting/fixed-assets', [FixedAssetController::class, 'index'])->name('accounting.fixed-assets');
    Route::get('accounting/aging', [AgingController::class, 'index'])->name('accounting.aging');
    Route::get('accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
    Route::post('accounting/budgets', [BudgetController::class, 'store'])->name('accounting.budgets.store');
    Route::get('accounting/master-data', [MasterDataController::class, 'index'])->name('accounting.master-data');
    Route::post('accounting/master-data/branches', [MasterDataController::class, 'storeBranch'])->name('accounting.master-data.branches.store');
    Route::post('accounting/master-data/accounts', [MasterDataController::class, 'storeAccount'])->name('accounting.master-data.accounts.store');
    Route::post('accounting/master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('accounting.master-data.customers.store');
    Route::post('accounting/master-data/vendors', [MasterDataController::class, 'storeVendor'])->name('accounting.master-data.vendors.store');
    Route::post('accounting/master-data/accounts-cash', [MasterDataController::class, 'storeCashAccount'])->name('accounting.master-data.accounts-cash.store');
    Route::post('accounting/master-data/products', [MasterDataController::class, 'storeInventoryItem'])->name('accounting.master-data.products.store');
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
