<?php

use App\Http\Controllers\Accounting\AgingController;
use App\Http\Controllers\Accounting\AssetRegisterController;
use App\Http\Controllers\Accounting\AssetReportController;
use App\Http\Controllers\Accounting\AttachmentController;
use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\CashBankRegisterController;
use App\Http\Controllers\Accounting\ClosingController;
use App\Http\Controllers\Accounting\DepreciationSimulationController;
use App\Http\Controllers\Accounting\ExpenseRegisterController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\InventoryController;
use App\Http\Controllers\Accounting\InventoryMovementController;
use App\Http\Controllers\Accounting\InventoryRegisterController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\LedgerController;
use App\Http\Controllers\Accounting\ManualJournalController;
use App\Http\Controllers\Accounting\ManualJournalRegisterController;
use App\Http\Controllers\Accounting\MasterDataController;
use App\Http\Controllers\Accounting\OpeningBalanceController;
use App\Http\Controllers\Accounting\OpeningCapitalRegisterController;
use App\Http\Controllers\Accounting\OpeningPositionController;
use App\Http\Controllers\Accounting\PayableReportController;
use App\Http\Controllers\Accounting\TaxReportController;
use App\Http\Controllers\Accounting\ReceivableController;
use App\Http\Controllers\Accounting\ReceivableRegisterController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\RevenueRegisterController;
use App\Http\Controllers\Accounting\SalesImportController;
use App\Http\Controllers\Accounting\TemplateController;
use App\Http\Controllers\Accounting\TransactionController;
use App\Http\Controllers\Accounting\TreasuryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PendingPageController;
use Illuminate\Support\Facades\Route;

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
    Route::get('master/accounts', [MasterDataController::class, 'accounts'])->name('master.accounts');
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
    Route::get('transactions/receivables', [ReceivableController::class, 'create'])->name('transactions.receivables.index');
    Route::post('transactions/receivables', [ReceivableController::class, 'store'])->name('transactions.receivables.store');
    Route::get('data/d-piutang', [ReceivableRegisterController::class, 'index'])->name('data.piutang.index');
    Route::get('data/d-piutang/export', [ReceivableRegisterController::class, 'export'])->name('data.piutang.export');
    Route::post('data/d-piutang/{receipt}/reverse', [ReceivableRegisterController::class, 'reverse'])->name('data.piutang.reverse');
    Route::post('data/d-piutang/{receipt}/attachments', [ReceivableRegisterController::class, 'attach'])->name('data.piutang.attachments.store');
    Route::get('transactions/cash-bank', [TreasuryController::class, 'create'])->name('transactions.cash-bank.index');
    Route::post('transactions/cash-bank', [TreasuryController::class, 'store'])->name('transactions.cash-bank.store');
    Route::get('data/d-kasbank', [CashBankRegisterController::class, 'index'])->name('data.kasbank.index');
    Route::get('data/d-kasbank/export', [CashBankRegisterController::class, 'export'])->name('data.kasbank.export');
    Route::post('data/d-kasbank/{treasury}/reverse', [CashBankRegisterController::class, 'reverse'])->name('data.kasbank.reverse');
    Route::post('data/d-kasbank/{treasury}/attachments', [CashBankRegisterController::class, 'attach'])->name('data.kasbank.attachments.store');
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
    Route::get('inputan/impor', [SalesImportController::class, 'index'])->name('inputan.impor');
    Route::get('inputan/impor/template.csv', [SalesImportController::class, 'templateCsv'])->name('inputan.impor.template-csv');
    Route::get('inputan/impor/template.xlsx', [SalesImportController::class, 'templateXlsx'])->name('inputan.impor.template-xlsx');
    Route::post('inputan/impor/preview', [SalesImportController::class, 'preview'])->name('inputan.impor.preview');
    Route::post('inputan/impor', [SalesImportController::class, 'store'])->name('inputan.impor.store');
    Route::post('inputan/impor/{batch}/reverse', [SalesImportController::class, 'reverse'])->name('inputan.impor.reverse');
    Route::get('inputan/persediaan', [InventoryMovementController::class, 'create'])->name('inputan.persediaan.create');
    Route::post('inputan/persediaan', [InventoryMovementController::class, 'store'])->name('inputan.persediaan.store');
    Route::get('data/d-persediaan', [InventoryRegisterController::class, 'index'])->name('data.persediaan.index');
    Route::get('data/d-persediaan/export', [InventoryRegisterController::class, 'export'])->name('data.persediaan.export');
    Route::post('data/d-persediaan/{movement}/reverse', [InventoryRegisterController::class, 'reverse'])->name('data.persediaan.reverse');
    Route::post('data/d-persediaan/{movement}/attachments', [InventoryRegisterController::class, 'attach'])->name('data.persediaan.attachments.store');
    Route::get('data/d-asset', [AssetRegisterController::class, 'index'])->name('data.assets.index');
    Route::get('data/d-asset/export', [AssetRegisterController::class, 'export'])->name('data.assets.export');
    Route::post('data/d-asset/{asset}/reverse', [AssetRegisterController::class, 'reverse'])->name('data.assets.reverse');
    Route::post('data/d-asset/{asset}/reverse-depreciation', [AssetRegisterController::class, 'reverseDepreciation'])->name('data.assets.reverse-depreciation');
    Route::post('data/d-asset/{asset}/attachments', [AssetRegisterController::class, 'attach'])->name('data.assets.attachments.store');
    Route::get('data/simulasi', [DepreciationSimulationController::class, 'index'])->name('data.simulasi.index');
    Route::post('data/simulasi', [DepreciationSimulationController::class, 'post'])->name('data.simulasi.post');
    Route::get('laporan/lap-asset', [AssetReportController::class, 'index'])->name('reports.assets');
    Route::get('accounting/inventory', [InventoryController::class, 'index'])->name('accounting.inventory');
    Route::get('accounting/fixed-assets', [FixedAssetController::class, 'index'])->name('accounting.fixed-assets');
    Route::get('accounting/aging', [AgingController::class, 'index'])->name('accounting.aging');
    Route::get('accounting/aging/export', [AgingController::class, 'export'])->name('accounting.aging.export');
    Route::get('laporan/lap-hutang', [PayableReportController::class, 'index'])->name('reports.payables');
    Route::get('laporan/lap-hutang/export', [PayableReportController::class, 'export'])->name('reports.payables.export');
    Route::get('laporan/lap-pajak', [TaxReportController::class, 'index'])->name('reports.taxes');
    Route::get('laporan/lap-pajak/export', [TaxReportController::class, 'export'])->name('reports.taxes.export');
    Route::get('accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
    Route::post('accounting/budgets', [BudgetController::class, 'store'])->name('accounting.budgets.store');
    Route::patch('accounting/budgets/{budget}', [BudgetController::class, 'update'])->name('accounting.budgets.update');
    Route::delete('accounting/budgets/{budget}', [BudgetController::class, 'destroy'])->name('accounting.budgets.destroy');
    Route::get('anggaran/ang-data', [BudgetController::class, 'data'])->name('anggaran.data');
    Route::get('anggaran/ang-data/export', [BudgetController::class, 'export'])->name('anggaran.data.export');
    Route::get('anggaran/ang-rekap', [BudgetController::class, 'recap'])->name('anggaran.rekap');
    Route::get('anggaran/ang-kinerja', [BudgetController::class, 'performance'])->name('anggaran.kinerja');
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
    Route::get('data/d-jm', [ManualJournalRegisterController::class, 'index'])->name('data.jm.index');
    Route::get('data/d-jm/export', [ManualJournalRegisterController::class, 'export'])->name('data.jm.export');
    Route::post('data/d-jm/{entry}/reverse', [ManualJournalRegisterController::class, 'reverse'])->name('data.jm.reverse');
    Route::post('data/d-jm/{entry}/attachments', [ManualJournalRegisterController::class, 'attach'])->name('data.jm.attachments.store');
    Route::get('data/d-modal', [OpeningCapitalRegisterController::class, 'index'])->name('data.modal.index');
    Route::get('data/d-modal/export', [OpeningCapitalRegisterController::class, 'export'])->name('data.modal.export');
    Route::post('data/d-modal/{opening}/reverse', [OpeningCapitalRegisterController::class, 'reverse'])->name('data.modal.reverse');
    Route::post('data/d-modal/{opening}/attachments', [OpeningCapitalRegisterController::class, 'attach'])->name('data.modal.attachments.store');

    Route::get('/modules/{section}', [ModuleController::class, 'show'])->name('modules.show');

    Route::get('/accounting/journals', [JournalController::class, 'index'])
        ->name('accounting.journals.index');
});

require __DIR__.'/settings.php';
