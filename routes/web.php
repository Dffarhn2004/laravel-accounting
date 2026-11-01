<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\Accounting\LedgerController;
use App\Http\Controllers\Accounting\TransactionController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingPageController;
use App\Http\Controllers\Accounting\ExpenseRegisterController;
use App\Http\Controllers\Accounting\AttachmentController;
use App\Http\Controllers\Accounting\RevenueRegisterController;
use App\Http\Controllers\Accounting\OpeningPositionController;
use App\Http\Controllers\Accounting\TemplateController;
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

    // Sidebar menus without a built page yet: one real, honest destination each.
    foreach (config('navigation.sections') as $section) {
        foreach ($section['type'] === 'group' ? $section['items'] : [$section] as $item) {
            if (($item['status'] ?? null) === 'pending') {
                Route::get($item['path'], PendingPageController::class)
                    ->defaults('menu', $item['id'])
                    ->name('nav.'.$item['id']);
            }
        }
    }

    Route::get('system/template', [TemplateController::class, 'index'])->name('system.template');
    Route::post('system/template/defaults', [TemplateController::class, 'loadDefaults'])->name('system.template.defaults');
    Route::post('system/template/branches', [TemplateController::class, 'storeBranch'])->name('system.template.branches.store');
    Route::patch('system/template/branches/{branch}', [TemplateController::class, 'updateBranch'])->name('system.template.branches.update');
    Route::post('system/template/years', [TemplateController::class, 'storeYear'])->name('system.template.years.store');
    Route::post('system/template/products', [TemplateController::class, 'storeProduct'])->name('system.template.products.store');
    Route::patch('system/template/products/{product}', [TemplateController::class, 'updateProduct'])->name('system.template.products.update');
    Route::post('system/template/inventory', [TemplateController::class, 'storeInventoryType'])->name('system.template.inventory.store');
    Route::patch('system/template/inventory/{item}', [TemplateController::class, 'updateInventoryType'])->name('system.template.inventory.update');
    Route::post('system/template/assets', [TemplateController::class, 'storeAssetCategory'])->name('system.template.assets.store');
    Route::patch('system/template/assets/{category}', [TemplateController::class, 'updateAssetCategory'])->name('system.template.assets.update');
    Route::post('system/template/options', [TemplateController::class, 'storeOption'])->name('system.template.options.store');
    Route::patch('system/template/options/{option}', [TemplateController::class, 'updateOption'])->name('system.template.options.update');
    Route::get('inputan/saldo', [OpeningPositionController::class, 'saldo'])->name('inputan.saldo');
    Route::post('inputan/saldo', [OpeningPositionController::class, 'storeSaldo'])->name('inputan.saldo.store');
    Route::get('inputan/modal', [OpeningPositionController::class, 'modal'])->name('inputan.modal');
    Route::post('inputan/modal', [OpeningPositionController::class, 'storeModal'])->name('inputan.modal.store');
    Route::get('master/accounts',[MasterDataController::class, 'accounts'])->name('master.accounts');
    Route::get('master/branches', [MasterDataController::class, 'branches'])->name('master.branches');
    Route::get('master/customers', [MasterDataController::class, 'customers'])->name('master.customers');
    Route::get('master/vendors', [MasterDataController::class, 'vendors'])->name('master.vendors');
    Route::get('master/accounts-cash', [MasterDataController::class, 'cashAccounts'])->name('master.accounts-cash');
    Route::get('master/products', [MasterDataController::class, 'products'])->name('master.products');
    Route::get('transactions/invoices', [RevenueRegisterController::class, 'index'])->name('transactions.invoices.index');
    Route::get('transactions/invoices/export', [RevenueRegisterController::class, 'export'])->name('transactions.invoices.export');
    Route::post('transactions/invoices/{invoice}/reverse', [RevenueRegisterController::class, 'reverse'])->name('transactions.invoices.reverse');
    Route::post('transactions/invoices/{invoice}/attachments', [RevenueRegisterController::class, 'attach'])->name('transactions.invoices.attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('transactions/invoices/create', [TransactionController::class, 'createInvoice'])->name('transactions.invoices.create');
    Route::get('transactions/invoices/{invoice}', [TransactionController::class, 'showInvoice'])->name('transactions.invoices.show');
    Route::get('transactions/invoices/{invoice}/print', [TransactionController::class, 'printInvoice'])->name('transactions.invoices.print');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('transactions/expenses', [ExpenseRegisterController::class, 'index'])->name('transactions.expenses.index');
    Route::get('transactions/expenses/export', [ExpenseRegisterController::class, 'export'])->name('transactions.expenses.export');
    Route::post('transactions/expenses/{expense}/reverse', [ExpenseRegisterController::class, 'reverse'])->name('transactions.expenses.reverse');
    Route::post('transactions/expenses/{expense}/attachments', [ExpenseRegisterController::class, 'attach'])->name('transactions.expenses.attachments.store');
    Route::get('transactions/expenses/create', [TransactionController::class, 'createExpense'])->name('transactions.expenses.create');
    Route::get('transactions/expenses/{expense}', [TransactionController::class, 'showExpense'])->name('transactions.expenses.show');
    Route::get('transactions/receivables', [TransactionController::class, 'receivables'])->name('transactions.receivables.index');
    Route::get('transactions/cash-bank', [TreasuryController::class, 'index'])->name('transactions.cash-bank.index');
    Route::get('transactions/fixed-assets', [FixedAssetController::class, 'index'])->name('transactions.fixed-assets.index');
    Route::post('transactions/fixed-assets', [FixedAssetController::class, 'store'])->name('transactions.fixed-assets.store');
    Route::post('transactions/fixed-assets/{asset}/depreciate', [FixedAssetController::class, 'depreciate'])->name('transactions.fixed-assets.depreciate');
    Route::get('accounting/journal', [JournalController::class, 'index'])->name('accounting.journal');
    Route::get('accounting/ledger', [LedgerController::class, 'index'])->name('accounting.ledger');
    Route::get('accounting/ledger/export', [LedgerController::class, 'export'])->name('accounting.ledger.export');
    Route::get('accounting/journals/export', [JournalController::class, 'export'])->name('accounting.journals.export');
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
    Route::get('reports/branch-profit-loss', [ReportController::class, 'branchProfitLoss'])->name('reports.branch-profit-loss');
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('accounting/inventory', [InventoryController::class, 'index'])->name('accounting.inventory');
    Route::get('accounting/fixed-assets', [FixedAssetController::class, 'index'])->name('accounting.fixed-assets');
    Route::get('accounting/aging', [AgingController::class, 'index'])->name('accounting.aging');
    Route::get('accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
    Route::post('accounting/budgets', [BudgetController::class, 'store'])->name('accounting.budgets.store');
    Route::get('accounting/master-data', [MasterDataController::class, 'index'])->name('accounting.master-data');
    Route::post('accounting/master-data/branches', [MasterDataController::class, 'storeBranch'])->name('accounting.master-data.branches.store');
    Route::post('accounting/master-data/accounts', [MasterDataController::class, 'storeAccount'])->name('accounting.master-data.accounts.store');
    Route::patch('accounting/master-data/accounts/{account}', [MasterDataController::class, 'updateAccount'])->name('accounting.master-data.accounts.update');
    Route::post('accounting/master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('accounting.master-data.customers.store');
    Route::post('accounting/master-data/vendors', [MasterDataController::class, 'storeVendor'])->name('accounting.master-data.vendors.store');
    Route::post('accounting/master-data/accounts-cash', [MasterDataController::class, 'storeCashAccount'])->name('accounting.master-data.accounts-cash.store');
    Route::post('accounting/master-data/products', [MasterDataController::class, 'storeInventoryItem'])->name('accounting.master-data.products.store');
    Route::post('accounting/master-data/branches/{branch}/deactivate', [MasterDataController::class, 'deactivateBranch'])->name('accounting.master-data.branches.deactivate');
    Route::post('accounting/master-data/accounts/{account}/deactivate', [MasterDataController::class, 'deactivateAccount'])->name('accounting.master-data.accounts.deactivate');
    Route::get('accounting/manual-journal', [ManualJournalController::class, 'create'])->name('accounting.manual-journal.create');
    Route::post('accounting/manual-journal', [ManualJournalController::class, 'store'])->name('accounting.manual-journal.store');

    Route::get('/modules/{section}', [ModuleController::class, 'show'])->name('modules.show');

    Route::get('/accounting/journals', [JournalController::class, 'index'])
        ->name('accounting.journals.index');
});

require __DIR__.'/settings.php';
