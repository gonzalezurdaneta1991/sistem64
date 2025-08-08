<?php

use App\Http\Controllers\SpaController;
use App\Http\Controllers\TableExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Routes
|--------------------------------------------------------------------------
|
| Here is where you can register SPA routes for your frontend. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "spa" middleware group.
|
*/

// pdf routes
Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/setup/brands/pdf', [TableExportController::class, 'brandsPDF'])->name('brands.pdf');
    Route::get('/setup/currencies/pdf', [TableExportController::class, 'currenciesPDF'])->name('currencies.pdf');
    Route::get('/setup/units/pdf', [TableExportController::class, 'unitsPDF'])->name('units.pdf');
    Route::get('/setup/vat-rates/pdf', [TableExportController::class, 'vatRatesPDF'])->name('vatRates.pdf');
    Route::get('/setup/roles/pdf', [TableExportController::class, 'rolesPDF'])->name('roles.pdf');
    Route::get('/setup/payment-methods/pdf', [TableExportController::class, 'paymentMethodsPDF'])->name('paymentMethods.pdf');

    Route::get('/expense-categories/pdf', [TableExportController::class, 'expCategoriesPDF'])->name('expCategories.pdf');
    Route::get('/expense-categories/export/excel', [TableExportController::class, 'expCategoriesExportExcel'])->name('expCategories.export/excel');
    Route::get('/expense-sub-categories/pdf', [TableExportController::class, 'expSubCategoriesPDF'])->name('expSubCategories.pdf');
    Route::get('/expense-sub-categories/export/excel', [TableExportController::class, 'expSubCategoriesExportExcel'])->name('expSubCategories.export.excel');
    Route::get('/expenses/pdf', [TableExportController::class, 'expensesPDF'])->name('expenses.pdf');
    Route::get('/expenses/export/excel', [TableExportController::class, 'expensesExportExcel'])->name('expenses.export.excel');

    Route::get('/purchases/pdf', [TableExportController::class, 'purchasesPDF'])->name('purchases.pdf');
    Route::get('/purchases/export/excel', [TableExportController::class, 'purchasesExportExcel'])->name('purchases.export.excel');
    Route::get('/purchase-returns/pdf', [TableExportController::class, 'purchaseReturnsPDF'])->name('purchaseReturns.pdf');
    Route::get('/purchase-returns/export/excel', [TableExportController::class, 'purchaseReturnsExportExcel'])->name('purchaseReturns.export.excel');

    Route::get('/quotations/pdf', [TableExportController::class, 'quotationsPDF'])->name('quotations.pdf');
    Route::get('/quotations/export/excel', [TableExportController::class, 'quotationsExportExcel'])->name('quotations.export.excel');
    Route::get('/invoices/pdf', [TableExportController::class, 'invoicePDF'])->name('invoices.pdf');
    Route::get('/invoices/export/export', [TableExportController::class, 'invoiceExportExcel'])->name('invoices.export.export');
    Route::get('/invoice-returns/pdf', [TableExportController::class, 'invoiceReturnPDF'])->name('invoiceReturns.pdf');
    Route::get('/invoice-returns/export/excel', [TableExportController::class, 'invoiceReturnExportExcel'])->name('invoiceReturns.export.excel');

    Route::get('/accounts/pdf', [TableExportController::class, 'accountsPDF'])->name('accounts.pdf');
    Route::get('//accounts/export/excel', [TableExportController::class, 'accountsExportExcel'])->name('accounts.export.excel');
    Route::get('/account-transactions/pdf/{slug}', [TableExportController::class, 'accountTransactionsPDF'])->name('account.transactions.pdf');
    Route::get('/cashbook/balance-adjustments/pdf', [TableExportController::class, 'nonInvoiceBalancesPDF'])->name('account.balances.pdf');
    Route::get('/cashbook/balance-adjustments/export/excel', [TableExportController::class, 'nonInvoiceBalancesExportExcel'])->name('account.balances.export.excel');
    Route::get('/cashbook/transfer-balances/pdf', [TableExportController::class, 'transferBalancesPDF'])->name('account.transferBalances.pdf');
    Route::get('/cashbook/transfer-balances/export/excel', [TableExportController::class, 'transferBalancesExportExcel'])->name('account.transferBalances.export.excel');
    Route::get('/cashbook/transactions/pdf', [TableExportController::class, 'transactionsPDF'])->name('transactions.pdf');
    Route::get('/cashbook/transactions/export/excel', [TableExportController::class, 'transactionsExportExcel'])->name('transactions.export.excel');

    Route::get('/payments/clients/non-invoice/pdf', [TableExportController::class, 'nonInvoicePaymentsPDF'])->name('nonInvoicePayments.pdf');
    Route::get('/payments/clients/non-invoice/export/excel', [TableExportController::class, 'nonInvoicePaymentsExportExcel'])->name('nonInvoicePayments.export.excel');
    Route::get('/payments/clients/invoice/pdf', [TableExportController::class, 'invoicePaymentsPDF'])->name('invoicePayments.pdf');
    Route::get('/payments/clients/invoice/export/excel', [TableExportController::class, 'invoicePaymentsExportExcel'])->name('invoicePayments.export.excel');
    Route::get('/payments/suppliers/non-purchase/pdf', [TableExportController::class, 'nonPurchasePaymentsPDF'])->name('nonPurchasePayments.pdf');
    Route::get('/payments/suppliers/non-purchase/export/excel', [TableExportController::class, 'nonPurchasePaymentsExportExcel'])->name('nonPurchasePayments.export.excel');
    Route::get('/payments/suppliers/purchase/pdf', [TableExportController::class, 'purchasePaymentsPDF'])->name('locSupplierPayments.pdf');
    Route::get('/payments/suppliers/purchase/export/excel', [TableExportController::class, 'purchasePaymentsExportExcel'])->name('locSupplierPayments.export.excel');

    Route::get('/loan-authorities/pdf', [TableExportController::class, 'loanAuthoritiesPDF'])->name('loanAuthorities.pdf');
    Route::get('/loan-authorities/export/excel', [TableExportController::class, 'loanAuthoritiesExportExcel'])->name('loanAuthorities.export.excel');
    Route::get('/loans/pdf', [TableExportController::class, 'loansPDF'])->name('loans.pdf');
    Route::get('/loans/export/excel', [TableExportController::class, 'loansExportExcel'])->name('loans.export.excel');
    Route::get('/loan-payments/pdf', [TableExportController::class, 'loanPaymentsPDF'])->name('loanPayments.pdf');
    Route::get('/loan-payments/export/excel', [TableExportController::class, 'loanPaymentsExportExcel'])->name('loanPayments.export.excel');

    Route::get('/asset-types/pdf', [TableExportController::class, 'assetTypesPDF'])->name('assetTypes.pdf');
    Route::get('/asset-types/export/excel', [TableExportController::class, 'assetTypesExportExcel'])->name('assetTypes.export.excel');
    Route::get('/assets/pdf', [TableExportController::class, 'assetsPDF'])->name('assets.pdf');
    Route::get('/assets/export/excel', [TableExportController::class, 'assetsExportExcel'])->name('assets.export.excel');

    Route::get('/payroll/pdf', [TableExportController::class, 'payrollPDF'])->name('payroll.pdf');
    Route::get('/payroll/export/excel', [TableExportController::class, 'payrollExportExcel'])->name('payroll.export.excel');

    Route::get('/clients/pdf', [TableExportController::class, 'clientsPDF'])->name('clients.pdf');
    Route::get('/clients/export/excel', [TableExportController::class, 'clientsExportExcel'])->name('clients.export.excel');
    Route::get('/suppliers/pdf', [TableExportController::class, 'suppliersPDF'])->name('suppliers.pdf');
    Route::get('/suppliers/export/excel', [TableExportController::class, 'suppliersExportExcel'])->name('suppliers.export.excel');

    Route::get('/departments/pdf', [TableExportController::class, 'departmentsPDF'])->name('departments.pdf');
    Route::get('/departments/export/excel', [TableExportController::class, 'departmentsExportExcel'])->name('departments.export.excel');
    Route::get('/employees/pdf', [TableExportController::class, 'employeesPDF'])->name('employees.pdf');
    Route::get('/employee/export/excel', [TableExportController::class, 'employeesExportExcel'])->name('employees.export.excel');
    Route::get('/increments/pdf', [TableExportController::class, 'incrementsPDF'])->name('increments.pdf');
    Route::get('/increments/export/excel', [TableExportController::class, 'incrementsExportExcel'])->name('increments.export.excel');

    Route::get('/product-categories/pdf', [TableExportController::class, 'productCategoriesPDF'])->name('productCategories.pdf');
    Route::get('/product-categories/export/excel', [TableExportController::class, 'productCategoriesExportExcel'])->name('productCategories.export.excel');
    Route::get('/product-sub-categories/pdf', [TableExportController::class, 'productSubCategoriesPDF'])->name('productSubCategories.pdf');
    Route::get('/product-sub-categories/export/excel', [TableExportController::class, 'productSubCategoriesExportExcel'])->name('productSubCategories.export.excel');
    Route::get('/products/pdf', [TableExportController::class, 'productsPDF'])->name('products.pdf');
    Route::get('/products/export/excel', [TableExportController::class, 'productsExportExcel'])->name('products.export.excel');

    Route::get('/inventory-adjustments/pdf', [TableExportController::class, 'inventoryAdjustmentsPDF'])->name('inventoryAdjustments.pdf');
    Route::get('/inventory/excel', [TableExportController::class, 'inventoryExcel'])->name('inventory.excel');
    Route::get('/inventory-adjustments/excel', [TableExportController::class, 'inventoryAdjustmentsExcel'])->name('inventoryAdjustments.excel');
    Route::get('/non-zero-inventory/pdf', [TableExportController::class, 'nonZeroInventoryPDF'])->name('nonZeroInventory.pdf');
    Route::get('/non-zero-inventory/export/excel', [TableExportController::class, 'nonZeroInventoryExportExcel'])->name('nonZeroInventory.export.excel');

    Route::get('/client-receivable-report/pdf', [TableExportController::class, 'clientReceivableReportPDF'])->name('clientReceivableReport.pdf');
    Route::get('/client-receivable-report/export/excel', [TableExportController::class, 'clientReceivableReportExportExcel'])->name('clientReceivableReport.export.excel');
    Route::get('/supplier-payable-report/pdf', [TableExportController::class, 'supplierPayableReportPDF'])->name('supplierPayableReport.pdf');
    Route::get('/supplier-payable-report/export/excel', [TableExportController::class, 'supplierPayableReportExportExcel'])->name('supplierPayableReport.export.excel');
    Route::get('/sales-by-user-report/export/excel', [TableExportController::class, 'salesByUserReportExportExcel'])->name('salesByUserReport.export.excel');
    Route::get('/collection-by-user-report/export/excel', [TableExportController::class, 'collectionByUserReportExportExcel'])->name('collectionByUserReport.export.excel');
});

// SPA routes
if (file_exists(storage_path('installed'))) {
    // SPA routes
    Route::get('{path}', SpaController::class)->where('path', '(.*)');
} else {
    Route::get('/', function () {
        return redirect('/install');
    });
}