<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Role;
use App\Models\Unit;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\VatRate;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\AssetType;
use App\Models\Quotation;
use App\Models\Department;
use App\Exports\ExportLoan;
use App\Models\LoanPayment;
use App\Exports\ExportAsset;
use Illuminate\Http\Request;
use App\Exports\ExportClient;
use App\Models\InvoiceReturn;
use App\Models\LoanAuthority;
use App\Models\PaymentMethod;
use App\Exports\ExpenseExport;
use App\Exports\ExportPayroll;
use App\Exports\ExportProduct;
use App\Exports\InvoiceExport;
use App\Models\BalanceTansfer;
use App\Models\InvoicePayment;
use App\Models\PurchaseReturn;
use App\Exports\ExportAccounts;
use App\Exports\ExportEmployee;
use App\Exports\ExportPurchase;
use App\Exports\ExportSupplier;
use App\Models\ExpenseCategory;
use App\Models\ProductCategory;
use App\Models\PurchasePayment;
use App\Models\SalaryIncrement;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\ExportAssetType;
use App\Exports\ExportInventory;
use App\Exports\ExportQuotation;
use App\Models\NonInvoicePayment;
use App\Exports\ExpCategoryExport;
use App\Exports\ExportLoanPayment;
use App\Models\AccountTransaction;
use App\Models\ExpenseSubCategory;
use App\Models\NonPurchasePayment;
use App\Models\ProductSubCategory;
use App\Models\InventoryAdjustment;
use App\Exports\ExportInvoiceReturn;
use App\Exports\ExportLoanAuthority;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportPurchaseReturn;
use App\Exports\ExpSubCategoryExport;
use App\Exports\ExportBalanceTransfer;
use App\Exports\ExportProductCategory;
use App\Exports\ExportSalaryIncrement;
use App\Exports\ExportNonZeroInventory;
use App\Exports\ExportSalesByUserReport;
use App\Exports\ExportAccountTransaction;
use App\Exports\ExportExployeeDepartment;
use App\Exports\ExportProductSubCategory;
use App\Exports\ExportInventoryAdjustment;
use App\Exports\ExportClientInvoicePayment;
use App\Http\Resources\InvoiceListResource;
use App\Exports\ExportSupplierPayableReport;
use App\Exports\ExportClientReceivableReport;
use App\Exports\ExportCollectionByUserReport;
use App\Exports\ExportClientNonInvoicePayment;
use App\Exports\ExportSupplierPurchasePayment;
use App\Http\Resources\InvoicePaymentResource;
use App\Exports\ExportAccountTransactionHistory;
use App\Exports\ExportSupplierNonPurchasePayment;

class TableExportController extends Controller
{
    // return all brands pdf
    public function brandsPDF()
    {
        // retrieve all records from db
        $data = Brand::latest()->get()->toArray();
        // share data to view
        view()->share('brands', $data);
        $pdf = PDF::loadView('pdf.brands', $data);
        // download PDF file with download method
        return $pdf->download('brands-list.pdf');
    }

    // return all currencies pdf
    public function currenciesPDF()
    {
        // retrieve all records from db
        $data = Currency::latest()->get()->toArray();
        // share data to view
        view()->share('currencies', $data);
        $pdf = PDF::loadView('pdf.currencies', $data);
        // download PDF file with download method
        return $pdf->download('currencies-list.pdf');
    }

    // return all units pdf
    public function unitsPDF()
    {
        // retrieve all records from db
        $data = Unit::latest()->get()->toArray();
        // share data to view
        view()->share('units', $data);
        $pdf = PDF::loadView('pdf.units', $data);
        // download PDF file with download method
        return $pdf->download('units-list.pdf');
    }

    // return all vat rates pdf
    public function vatRatesPDF()
    {
        // retrieve all records from db
        $data = VatRate::latest()->get()->toArray();
        // share data to view
        view()->share('vatRates', $data);
        $pdf = PDF::loadView('pdf.vat-rates', $data);
        // download PDF file with download method
        return $pdf->download('vat-rates-list.pdf');
    }

    // return all roles pdf
    public function rolesPDF()
    {
        // retrieve all records from db
        $data = Role::latest()->get()->toArray();
        // share data to view
        view()->share('roles', $data);
        $pdf = PDF::loadView('pdf.roles', $data);
        // download PDF file with download method
        return $pdf->download('roles-list.pdf');
    }

    // return all payment methods pdf
    public function paymentMethodsPDF()
    {
        // retrieve all records from db
        $data = PaymentMethod::latest()->get()->toArray();
        // share data to view
        view()->share('paymentMethods', $data);
        $pdf = PDF::loadView('pdf.payment-methods', $data);
        // download PDF file with download method
        return $pdf->download('payment-methods-list.pdf');
    }

    // return expense category pdf
    public function expCategoriesPDF()
    {
        // retrieve all records from db
        $data = ExpenseCategory::latest()->get()->toArray();
        // share data to view
        view()->share('categories', $data);
        $pdf = PDF::loadView('pdf.exp-categories', $data);
        // download PDF file with download method
        return $pdf->download('exp-categories-list.pdf');
    }

    // return expense category export
    public function expCategoriesExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExpCategoryExport($term), 'ExpenseCategory.xlsx');
    }

    // return expense sub category pdf
    public function expSubCategoriesPDF()
    {
        // retrieve all records from db
        $data = ExpenseSubCategory::with('expCategory')->latest()->get()->toArray();
        // share data to view
        view()->share('categories', $data);
        $pdf = PDF::loadView('pdf.exp-sub-categories', $data);
        // download PDF file with download method
        return $pdf->download('exp-sub-categories-list.pdf');
    }

    // return expense sub category export
    public function expSubCategoriesExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExpSubCategoryExport($term), 'ExpenseSubCategory.xlsx');
    }

    // return expense pdf
    public function expensesPDF()
    {
        // retrieve all records from db
        $data = Expense::with('expSubCategory.expCategory', 'expTransaction.cashbookAccount')->latest()->get()->toArray();
        // share data to view
        view()->share('expenses', $data);
        $pdf = PDF::loadView('pdf.expenses', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('expenses-list.pdf');
    }

    // return expense export
    public function expensesExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExpenseExport($startDate, $endDate, $term), 'expenses.xlsx');
    }

    // return purchases pdf
    public function purchasesPDF()
    {
        // retrieve all records from db
        $data = Purchase::with('supplier')->latest()->get()->toArray();
        // share data to view
        view()->share('purchases', $data);
        $pdf = PDF::loadView('pdf.purchases', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('purchases-list.pdf');
    }

    // return purchases excel
    public function purchasesExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportPurchase($startDate, $endDate, $term), 'purchases.xlsx');
    }

    // return purchase returns pdf
    public function purchaseReturnsPDF()
    {
        // retrieve all records from db
        $data = PurchaseReturn::with('purchase.supplier')->latest()->get()->toArray();
        // share data to view
        view()->share('returns', $data);
        $pdf = PDF::loadView('pdf.purchase-returns', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('purchase-returns-list.pdf');
    }

    // return purchase returns export
    public function purchaseReturnsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportPurchaseReturn($startDate, $endDate, $term), 'PurchaseReturn.xlsx');
    }

    // return quotation pdf
    public function quotationsPDF()
    {
        // retrieve all records from db
        $data = Quotation::with('client')->latest()->get()->toArray();
        // share data to view
        view()->share('quotations', $data);
        $pdf = PDF::loadView('pdf.quotations', $data);
        // download PDF file with download method
        return $pdf->download('quotation-list.pdf');
    }

    // return quotation export
    public function quotationsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportQuotation($startDate, $endDate, $term), 'SalesQuotation.xlsx');
    }

    // return invoice pdf
    public function invoicePDF()
    {
        // retrieve all records from db
        $data = Invoice::with('client', 'invoicePayments')->latest()->get()->toArray();
        // share data to view
        view()->share('invoices', $data);
        $pdf = PDF::loadView('pdf.invoices', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('invoice-list.pdf');
    }

    // return invoice pdf
    public function invoiceExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new InvoiceExport($startDate, $endDate, $term), 'invoices.xlsx');
    }

    // return invoice pdf
    public function invoiceReturnPDF()
    {
        // retrieve all records from db
        $data = InvoiceReturn::with('invoice.client')->latest()->get()->toArray();
        // share data to view
        view()->share('returns', $data);
        $pdf = PDF::loadView('pdf.invoice-returns', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('invoice-return-list.pdf');
    }

    // return invoice export
    public function invoiceReturnExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportInvoiceReturn($startDate, $endDate, $term), 'InvoiceReturn.xlsx');
    }


    // return accounts pdf
    public function accountsPDF()
    {
        // retrieve all records from db
        $data = Account::with('balanceTransactions')->latest()->get()->toArray();
        // share data to view
        view()->share('accounts', $data);
        $pdf = PDF::loadView('pdf.accounts', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('account-list.pdf');
    }

    // return accounts export
    public function accountsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportAccounts($startDate, $endDate, $term), 'accounts.xlsx');
    }

    // return account transaction pdf
    public function accountTransactionsPDF($slug)
    {
        $account = Account::where('slug', $slug)->first();
        $data = AccountTransaction::with('cashbookAccount', 'user')->where('account_id', $account->id)->orderBy('created_at', 'asc')->get()->toArray();
        // share data to view
        view()->share('transactions', $data);
        $pdf = PDF::loadView('pdf.transactions', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download($account->account_number.'-ledger.pdf');
    }

    // return non invoice add balances pdf
    public function nonInvoiceBalancesPDF()
    {
        $data = AccountTransaction::with('cashbookAccount')->where('reason', 'LIKE', 'Non invoice balance added%')->latest()->get()->toArray();
        // share data to view
        view()->share('balances', $data);
        $pdf = PDF::loadView('pdf.non-invoice-balances', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('account-transaction-list.pdf');
    }


    // return balances adjustment export
    public function nonInvoiceBalancesExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExportAccountTransaction($term), 'BalanceAdjustments.xlsx');
    }

    // return transfer balances pdf
    public function transferBalancesPDF()
    {
        $data = BalanceTansfer::with('debitTransaction.cashbookAccount', 'creditTransaction.cashbookAccount', 'user')->latest()->get()->toArray();
        // share data to view
        view()->share('transfers', $data);
        $pdf = PDF::loadView('pdf.transfer-balances', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('balance-transfer-list.pdf');
    }

    // return transfer balances export
    public function transferBalancesExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportBalanceTransfer($startDate, $endDate, $term), 'BalanceTransfer.xlsx');
    }

    // return transactions PDF
    public function transactionsPDF()
    {
        $data = AccountTransaction::with('cashbookAccount', 'user')->latest()->get()->toArray();
        // share data to view
        view()->share('transactions', $data);
        $pdf = PDF::loadView('pdf.all-transactions', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('transaction-list.pdf');
    }

    // return transactions export
    public function transactionsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportAccountTransactionHistory($startDate, $endDate, $term), 'TransactionHistory.xlsx');
    }

    // return client non invoice payment pdf
    public function nonInvoicePaymentsPDF()
    {
        $data = NonInvoicePayment::with('client', 'paymentTransaction.cashbookAccount')->latest()->get()->toArray();
        // share data to view
        view()->share('payments', $data);
        $pdf = PDF::loadView('pdf.non-invoice-payments', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('non-invoice-payment-list.pdf');
    }

    // return client non invoice payment export
    public function nonInvoicePaymentsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportClientNonInvoicePayment($startDate, $endDate, $term), 'ClientNonInvoicePayments.xlsx');
    }

    // return client invoice payment pdf
    public function invoicePaymentsPDF()
    {
        $data = InvoicePayment::with('invoice.client', 'invoicePaymentTransaction.cashbookAccount', 'user')->latest()->get()->toArray();
        // share data to view
        view()->share('payments', $data);
        $pdf = PDF::loadView('pdf.invoice-payments', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('invoice-payment-list.pdf');
    }

    // return client invoice payment export
    public function invoicePaymentsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportClientInvoicePayment($startDate, $endDate, $term), 'ClientInvoicePayment.xlsx');
    }

    // return supplier non purchase payment pdf
    public function nonPurchasePaymentsPDF()
    {
        $data = NonPurchasePayment::with('supplier', 'paymentTransaction.cashbookAccount')->latest()->get()->toArray();
        // share data to view
        view()->share('payments', $data);
        $pdf = PDF::loadView('pdf.non-purchase-payments', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('non-purchase-payment-list.pdf');
    }

    // return supplier non purchase payment export
    public function nonPurchasePaymentsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportSupplierNonPurchasePayment($startDate, $endDate, $term), 'SupplierNonPurchasePayment.xlsx');
    }

    // return supplier purchase payment pdf
    public function purchasePaymentsPDF()
    {
        $data = PurchasePayment::with('purchase.supplier', 'purchasePaymentTransaction.cashbookAccount', 'user')->latest()->get()->toArray();
        // share data to view
        view()->share('payments', $data);
        $pdf = PDF::loadView('pdf.purchase-payments', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('purchase-payments-list.pdf');
    }

    // return supplier purchase payment export
    public function purchasePaymentsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportSupplierPurchasePayment($startDate, $endDate, $term), 'SupplierPurchasePayment.xlsx');
    }

    // return loan authorities pdf
    public function loanAuthoritiesPDF()
    {
        // retrieve all records from db
        $data = LoanAuthority::latest()->get()->toArray();
        // share data to view
        view()->share('loanAuthorities', $data);
        $pdf = PDF::loadView('pdf.loan-authorities', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('loan-authority-list.pdf');
    }

    // return loan authorities export
    public function loanAuthoritiesExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExportLoanAuthority($term), 'LoanAuthorities .xlsx');
    }


    // return loans pdf
    public function loansPDF()
    {
        // retrieve all records from db
        $data = Loan::with('loanAuthority', 'loanPayments', 'loanTransaction.cashbookAccount')->latest()->get();
        // share data to view
        view()->share('loans', $data);
        $pdf = PDF::loadView('pdf.loans', compact('data'))->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('loan-list.pdf');
    }

    // return loans export
    public function loansExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportLoan($startDate, $endDate, $term), 'Loans.xlsx');
    }


    // return loan payments pdf
    public function loanPaymentsPDF()
    {
        // retrieve all records from db
        $data = LoanPayment::with('loan.loanAuthority', 'loanPaymentTransaction.cashbookAccount')->latest()->get();
        // share data to view
        view()->share('loanPayments', $data);
        $pdf = PDF::loadView('pdf.loan-payments', compact('data'))->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('loan-payment-list.pdf');
    }

    // return loan payments export
    public function loanPaymentsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportLoanPayment($startDate, $endDate, $term), 'LoanPayments.xlsx');
    }
  

    // return asset types pdf
    public function assetTypesPDF()
    {
        // retrieve all records from db
        $data = AssetType::latest()->get()->toArray();
        // share data to view
        view()->share('assetTypes', $data);
        $pdf = PDF::loadView('pdf.asset-types', $data);
        // download PDF file with download method
        return $pdf->download('asset-type-list.pdf');
    }

        // return asset types export
    public function assetTypesExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExportAssetType($term), 'AssetTypes.xlsx');
    }


    // return assets pdf
    public function assetsPDF()
    {
        // retrieve all records from db
        $data = Asset::with('assetType')->latest()->get()->toArray();
        // share data to view
        view()->share('assets', $data);
        $pdf = PDF::loadView('pdf.assets', $data);
        // download PDF file with download method
        return $pdf->download('asset-list.pdf');
    }

    // return assets export
    public function assetsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportAsset($startDate, $endDate, $term), 'Assets.xlsx');
    }

    // return payroll pdf
    public function payrollPDF()
    {
        // retrieve all records from db
        $data = Payroll::with('employee.department', 'payrollTransaction.cashbookAccount')->latest()->get()->toArray();
        // share data to view
        view()->share('allPayroll', $data);
        $pdf = PDF::loadView('pdf.payroll', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('payroll-list.pdf');
    }

    // return payroll export
    public function payrollExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportPayroll($startDate, $endDate, $term), 'Payroll.xlsx');
    }

    // return clients pdf
    public function clientsPDF()
    {
        // retrieve all records from db
        $data = Client::latest()->get()->toArray();
        // share data to view
        view()->share('clients', $data);
        $pdf = PDF::loadView('pdf.clients', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('client-list.pdf');
    }

       // return clients export
       public function clientsExportExcel(Request $request)
       {
           $startDate = $request->input('start_date');
           $endDate = $request->input('end_date');
           $term = $request->input('term');
   
           return Excel::download(new ExportClient($startDate, $endDate, $term), 'Clients.xlsx');
       }

    // return suppliers pdf
    public function suppliersPDF()
    {
        // retrieve all records from db
        $data = Supplier::latest()->get()->toArray();
        // share data to view
        view()->share('suppliers', $data);
        $pdf = PDF::loadView('pdf.suppliers', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('supplier-list.pdf');
    }

     // return suppliers export
     public function suppliersExportExcel(Request $request)
     {
         $startDate = $request->input('start_date');
         $endDate = $request->input('end_date');
         $term = $request->input('term');
 
         return Excel::download(new ExportSupplier($startDate, $endDate, $term), 'Suppliers.xlsx');
     }

    // return departments pdf
    public function departmentsPDF()
    {
        // retrieve all records from db
        $data = Department::latest()->get()->toArray();
        // share data to view
        view()->share('departments', $data);
        $pdf = PDF::loadView('pdf.departments', $data);
        // download PDF file with download method
        return $pdf->download('department-list.pdf');
    }

    // return departments export
    public function departmentsExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExportExployeeDepartment($term), 'EmpDepartment.xlsx');
    }
    

    // return employees pdf
    public function employeesPDF()
    {
        // retrieve all records from db
        $data = Employee::with('department', 'user')->latest()->get()->toArray();
        // share data to view
        view()->share('employees', $data);
        $pdf = PDF::loadView('pdf.employees', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('employee-list.pdf');
    }

    // return employees export
    public function employeesExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportEmployee($startDate, $endDate, $term), 'Employee.xlsx');
    }

    // return increments pdf
    public function incrementsPDF()
    {
        // retrieve all records from db
        $data = SalaryIncrement::with('employee.department')->latest()->get()->toArray();
        // share data to view
        view()->share('salIncrements', $data);
        $pdf = PDF::loadView('pdf.increments', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('increment-list.pdf');
    }

    // return increments export
    public function incrementsExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportSalaryIncrement($startDate, $endDate, $term), 'EmpIncrement.xlsx');
    }

    // return product categories pdf
    public function productCategoriesPDF()
    {
        // retrieve all records from db
        $data = ProductCategory::latest()->get()->toArray();
        // share data to view
        view()->share('productCategories', $data);
        $pdf = PDF::loadView('pdf.product-categories', $data);
        // download PDF file with download method
        return $pdf->download('product-category-list.pdf');
    }

      // return product categories export
      public function productCategoriesExportExcel(Request $request)
      {
          $term = $request->input('term');
          return Excel::download(new ExportProductCategory($term), 'ProductCategories.xlsx');
      }  

    // return product sub categories pdf
    public function productSubCategoriesPDF()
    {
        // retrieve all records from db
        $data = ProductSubCategory::with('category')->latest()->get()->toArray();
        // share data to view
        view()->share('productsubCategories', $data);
        $pdf = PDF::loadView('pdf.product-sub-categories', $data);
        // download PDF file with download method
        return $pdf->download('product-sub-category-list.pdf');
    }

      // return product sub categories export
      public function productSubCategoriesExportExcel(Request $request)
      {
          $term = $request->input('term');
          return Excel::download(new ExportProductSubCategory($term), 'ProductSubCategory.xlsx');
      }

    // return product products pdf
    public function productsPDF()
    {
        // retrieve all records from db
        $data = Product::with('proSubCategory.category', 'productUnit')->latest()->get();
        // share data to view
        view()->share('products', $data);
        $pdf = PDF::loadView('pdf.products', compact('data'))->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('product-list.pdf');
    }

    // return product export
    public function productsExportExcel(Request $request)
    {
        $term = $request->input('term');
        return Excel::download(new ExportProduct($term), 'Products.xlsx');
    }

    // return inventoryAdjustments PDF
    public function inventoryAdjustmentsPDF()
    {
        // retrieve all records from db
        $data = InventoryAdjustment::latest()->get();
        // share data to view
        view()->share('adjustments', $data);
        $pdf = PDF::loadView('pdf.adjustments', compact('data'))->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('inventory-adjustment-list.pdf');
    }

       // return inventory excel
       public function inventoryExcel (Request $request)
       {
           $term = $request->input('term');
           return Excel::download(new ExportInventory($term), 'Inventory.xlsx');
       }

    // return inventoryAdjustments excel
    public function inventoryAdjustmentsExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportInventoryAdjustment($startDate, $endDate, $term), 'InventoryAdjustment.xlsx');
    }

     // return non zero inventory export
    public function nonZeroInventoryExportExcel(Request $request){
        $term = $request->input('term');
        return Excel::download(new ExportNonZeroInventory($term), 'NonZeroInventory.xlsx');
    }

    // return non zero inventory products
    public function nonZeroInventoryPDF()
    {
        // retrieve all records from db
        $data = [];
        Product::where('inventory_count', '>', 0)
        ->with('proSubCategory.category', 'productUnit')
        ->orderBy('code', 'ASC')
        ->chunk(200, function ($products) use (&$data) {
            foreach ($products as $product) {
                // Process each product and add it to the $data array
                $data[] = $product;
            }
        });

        // share data to view
        view()->share('products', $data);
        $pdf = PDF::loadView('pdf.non-zero-inventory', compact('data'))->setPaper('a4', 'landscape');
        // download PDF file with download method

        return $pdf->stream('non-zero-inventory-list.pdf');
    }


    // return client receivable report PDF
    public function clientReceivableReportPDF()
    {
        // retrieve all records from db
        $data = Client::latest()->get()->toArray();
        // share data to view
        view()->share('clients', $data);
        $pdf = PDF::loadView('pdf.client-receivable-report', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('client-receivable-report.pdf');
    }


    // return client receivable report export
    public function clientReceivableReportExportExcel(Request $request){
        $term = $request->input('term');
        return Excel::download(new ExportClientReceivableReport($term), 'ClientReceivableReport.xlsx');
    }

    // return supplier payable report PDF
    public function supplierPayableReportPDF()
    {
        // retrieve all records from db
        $data = Supplier::latest()->get()->toArray();
        // share data to view
        view()->share('suppliers', $data);
        $pdf = PDF::loadView('pdf.supplier-payable-report', $data)->setPaper('a4', 'landscape');
        // download PDF file with download method
        return $pdf->download('supplier-payable-report.pdf');
    }


      // return supplier payable report export
      public function supplierPayableReportExportExcel(Request $request){
        $term = $request->input('term');
        return Excel::download(new ExportSupplierPayableReport($term), 'SupplierPayableReport.xlsx');
    }

    // return salesByUser export
    public function salesByUserReportExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportSalesByUserReport($startDate, $endDate, $term), 'SalesByUserReport.xlsx');
    }

    // return collectionByUser export
    public function collectionByUserReportExportExcel(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $term = $request->input('term');

        return Excel::download(new ExportCollectionByUserReport($startDate, $endDate, $term), 'CollectionByUserReport.xlsx');
    }
}