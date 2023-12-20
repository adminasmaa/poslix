<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\TaxController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PricingGroupController;
use App\Http\Controllers\QuotationsListController;
use App\Http\Controllers\TailoringPackageTypeController;
use App\Http\Controllers\TailoringOrderController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\LocationReportController;
use App\Http\Controllers\productAllPricesController;
use App\Http\Controllers\DigitalMenuController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//, 'middleware' => ['jwt']
Route::group(['as' => 'api.', 'middleware' => ['jwt', 'checkPermissions']], function () {
    Route::get('/currencies', function () {
        $currencies = DB::table('currencies')
            ->select(
                'currencies.currency as currency_name',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            )->get();
        return customResponse($currencies, 200);
    });

    Route::post('/add-user', [BusinessController::class, 'addUser'])->name('business.user/add');
    Route::get('/users', [BusinessController::class, 'getUsers'])->name('business.users/view');
    Route::delete('/delete-user/{userId}', [BusinessController::class, 'deleteUser'])->name('business.user/delete');
    Route::put('/update-user/{userId}', [BusinessController::class, 'updateUser'])->name('business.user/update');

    Route::get('/permissions', [PermissionController::class, 'getPermissions'])->name('permission.permissions/view');
    Route::get('/permissions/{stuff_id}', [PermissionController::class, 'getPermission'])->name('permission.permissions/show');
    Route::post('/permissions', [PermissionController::class, 'storePermission'])->name('permission.permissions/add');
    Route::put('/permissions/{stuff_id}', [PermissionController::class, 'updatePermission'])->name('permission.permissions/update');
    Route::delete('/permissions/{stuff_id}', [PermissionController::class, 'deletePermission'])->name('permission.permissions/delete');
    Route::get('/permissions/list', [PermissionController::class, 'getPermissionsList'])->name('permission.permissions/list');
    Route::post('/permissions/assign', [PermissionController::class, 'assignPermissions'])->name('permission.permissions/assign');

    // apis
    Route::get('/categories/{location_id}', [CategoryController::class, 'getCategories'])->name('settings.categories.categories/view');
    Route::get('/categories/pricing-group/{location_id}/{customer_id}', [CategoryController::class, 'getCategoriesPricingGroup'])->name('settings.categories.categories/view');
    Route::get('/categories/{id}/show', [CategoryController::class, 'getCategory'])->name('settings.categories.categories/show');
    Route::post('/categories/{location_id}', [CategoryController::class, 'setCategories'])->name('settings.categories.categories/add');
    Route::put('/categories/{id}', [CategoryController::class, 'updateCategory'])->name('settings.categories.categories/update');
    Route::delete('/categories/{id}', [CategoryController::class, 'deleteCategory'])->name('settings.categories.categories/delete');

    Route::get('/units', [UnitController::class, 'getUnits'])->name('units');

    Route::get('/brands/{location_id}', [BrandController::class, 'getBrands'])->name('settings.brands.brands/view');
    Route::get('/brands/pricing-group/{location_id}/{customer_id}', [BrandController::class, 'getBrandsPricingGroup'])->name('settings.brands.brands/view');
    Route::get('/brands/{id}/show', [BrandController::class, 'getBrand'])->name('settings.brands.brands/show');
    Route::post('/brands/{location_id}', [BrandController::class, 'setBrands'])->name('settings.brands.brands/add');
    Route::put('/brands/{id}', [BrandController::class, 'updateBrand'])->name('settings.brands.brands/update');
    Route::delete('/brands/{id}', [BrandController::class, 'deleteBrand'])->name('settings.brands.brands/delete');

    Route::get("/taxes/{location_id}", [TaxController::class, 'getTaxes'])->name("settings.taxes.taxes/view");
    Route::post("/taxes/{location_id}", [TaxController::class, 'setTax'])->name("settings.taxes.taxes/add");
    Route::get("/taxes/{id}/show", [TaxController::class, 'getTax'])->name("settings.taxes.taxes/show");
    Route::put("/taxes/{id}", [TaxController::class, 'updateTax'])->name("settings.taxes.taxes/update");
    Route::delete("/taxes/{id}", [TaxController::class, 'deleteTax'])->name("settings.taxes.taxes/delete");

    // tailoring packages types
    Route::get('/tailoring-package-types/{location_id}', [TailoringPackageTypeController::class, 'index'])->name('tailoring.tailoring-package-types/view');
    Route::get('/tailoring-package-types/{id}/show', [TailoringPackageTypeController::class, 'show'])->name('tailoring.tailoring-package-type/show');
    Route::post('/tailoring-package-types', [TailoringPackageTypeController::class, 'store'])->name('tailoring.tailoring-package-type/add');
    Route::put('/tailoring-package-types/{id}', [TailoringPackageTypeController::class, 'update'])->name('tailoring.tailoring-package-type/update');
    Route::delete('/tailoring-package-types/{id}', [TailoringPackageTypeController::class, 'destroy'])->name('tailoring.tailoring-package-type/delete');

    Route::get('/tailoring-types-extra/{location_id}', [TailoringPackageTypeController::class, 'getTailoringTypeExtras'])->name('tailoring.tailoring-types-extra/view');
    Route::post('/tailoring-types-extra', [TailoringPackageTypeController::class, 'setTailoringTypeExtra'])->name('tailoring.tailoring-types-extra/add');
    Route::put('/tailoring-types-extra/{id}', [TailoringPackageTypeController::class, 'updateTailoringTypeExtra'])->name('tailoring.tailoring-types-extra/update');
    Route::delete('/tailoring-types-extra/{id}', [TailoringPackageTypeController::class, 'deleteTailoringTypeExtra'])->name('tailoring.tailoring-types-extra/delete');

    Route::get('/package-type/{location_id}', [ProductController::class, 'getPackageTypes'])->name('inventory.products.packages-types/view');
    Route::get('/products/{location_id}', [ProductController::class, 'getProducts'])->name('inventory.products.products/view');
    Route::get("/products/pricing-group/{location_id}/{customer_id}", [ProductController::class, 'getProductsPricingGroup'])->name("inventory.products.products/view");
    Route::get("/products/{product}/show", [ProductController::class, 'getProduct'])->name("inventory.products.products/show");
    Route::post("/products", [ProductController::class, 'setProducts'])->name('inventory.products.products/add');
    Route::put("/products/{product}", [ProductController::class, 'updateProduct'])->name("inventory.products.products/update");
    Route::put("/products/{product}/status", [ProductController::class, 'updateProductStatus'])->name("inventory.products.products/update-status");
    Route::delete("/products/delete", [ProductController::class, 'deleteProduct'])->name("inventory.products.products/delete");
    Route::delete('/products/delete-all', [ProductController::class, 'deleteAll'])->name("inventory.products.products/deleteAll");
    Route::get("/products/{productId}/transfer", [ProductController::class, 'transferProduct'])->name("inventory.transfers.product/transfer");
    Route::post("/products/{location_id}/import", [ProductController::class, 'import'])->name("inventory.products.products/import");
    Route::get("/products/search/{location_id}", [ProductController::class, 'searchProducts'])->name("inventory.products.products/search");
    Route::get("/fabrics/{location_id}", [ProductController::class, 'getFabrics'])->name("inventory.products.products/fabrics");
    
    Route::get('/products-all-prices/{location_id}', [productAllPricesController::class, 'getProductsAllPrices'])->name('inventory.products.products/view-productAllPrices');
    Route::get('/products-all-prices/{product}/show', [productAllPricesController::class, 'getProduct'])->name('inventory.products.products/show-productAllPrices');


    Route::get('/customers/{location_id}', [CustomerController::class, 'getCustomers'])->name('customers.customers/view');
    Route::get('/customers/{customer}/show', [CustomerController::class, 'getCustomer'])->name('customers.customers/show');
    Route::post('/customers/{location_id}', [CustomerController::class, 'setCustomers'])->name('customers.customers/add');
    Route::put('/customers/{customer}', [CustomerController::class, 'updateCustomer'])->name('customers.customers/update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'deleteCustomer'])->name('customers.customers/delete');


    Route::get("/currencies", [CurrencyController::class, 'getCurrencies'])->name("pos.pos/currencies");

    Route::get("/expenses-categories/{location_id}", [ExpenseCategoryController::class, 'getCategories'])->name("inventory.expense-category.expense-category/view");
    Route::get("/expenses-categories/{id}/show", [ExpenseCategoryController::class, 'getCategory'])->name("inventory.expense-category.expense-category/show");
    Route::post("/expenses-categories/{location_id}", [ExpenseCategoryController::class, 'setCategory'])->name("inventory.expense-category.expense-category/add");
    Route::put("/expenses-categories/{id}", [ExpenseCategoryController::class, 'updateCategory'])->name("inventory.expense-category.expense-category/update");
    Route::delete("/expenses-categories/{id}", [ExpenseCategoryController::class, 'deleteCategory'])->name("inventory.expense-category.expense-category/delete");

    Route::get("expenses/{location_id}", [ExpenseController::class, 'getExpenses'])->name("inventory.expenses.expenses/view");
    Route::get("expenses/{expense}/show", [ExpenseController::class, 'getExpense'])->name("inventory.expenses.expenses/show");
    Route::post("expenses/{location_id}", [ExpenseController::class, 'setExpense'])->name("inventory.expenses.expenses/add");
    Route::put("expenses/update-expense/{expense}", [ExpenseController::class, 'updateExpense'])->name("inventory.expenses.expenses/update");
    Route::delete("expenses/{expense}", [ExpenseController::class, 'deleteExpense'])->name("inventory.expenses.expenses/delete");

    Route::get('/purchase/{location_id}', [PurchaseController::class, 'getPurchases'])->name('inventory.purchases.purchases/view');
    Route::get('/purchase/{purchase}/show', [PurchaseController::class, 'getPurchase'])->name('inventory.purchases.purchases/show');
    Route::post('/purchase/{location_id}', [PurchaseController::class, 'setPurchase'])->name('inventory.purchases.purchases/add');
    Route::put('/purchase/{purchase}/update', [PurchaseController::class, 'updatePurchase'])->name('inventory.purchases.purchases/update');
    Route::put('/purchase/{purchase}/payment', [PurchaseController::class, 'updatePurchasePayment'])->name('inventory.purchases.purchases/update');
    Route::delete('/purchase/{purchase}', [PurchaseController::class, 'deletePurchase'])->name('inventory.purchases.purchases/delete');
    Route::put('/purchase/complete-purchase/{purchaseId}', [PurchaseController::class, 'completePurchase'])->name('inventory.purchases.purchases/complete');

    Route::get('/transfer/{location_id}', [TransferController::class, 'getTransfers'])->name('inventory.transfers.transfers/view');
    Route::get('/transfer/{transfer}/show', [TransferController::class, 'getTransfer'])->name('inventory.transfers.transfers/show');
    Route::post('/transfer', [TransferController::class, 'setTransfer'])->name('inventory.transfers.transfers/add');
    Route::put('/transfer/{transfer}', [TransferController::class, 'updateTransfer'])->name('inventory.transfers.transfers/update');
    Route::put('/transfer/{transfer}/payment', [TransferController::class, 'updateTransferPayment'])->name('inventory.transfers.transfers/update-payment');
    Route::delete('/transfer/{transfer}', [TransferController::class, 'deleteTransfer'])->name('inventory.transfers.transfers/delete');
    Route::get('/transfer/received/{transfer}', [TransferController::class, 'receivedTransfer'])->name('inventory.transfers.transfers/received');

    Route::get('/location-report', [LocationReportController::class, 'report'])->name('business.locations.locations/report');

    Route::post('/checkout', CheckoutController::class)->name('pos.pos/checkout');

    Route::get("/tailoring/orders/{location_id}", [TailoringOrderController::class, 'getOrders'])->name("tailoring.orders.orders/view");
    Route::put("/tailoring/orders/status", [TailoringOrderController::class, 'updateOrderStatus'])->name("tailoring.orders.orders/update");

    Route::get('/registration/{location_id}/close', [RegistrationController::class, 'beforeClose'])->name('pos.register.before-close/register');
    Route::post('/registration/{location_id}/close', [RegistrationController::class, 'closeRegistration'])->name('pos.register.close/register');
    Route::post('/registration/{location_id}/open', [RegistrationController::class, 'openRegistration'])->name('pos.register.open/register');

    Route::post('/appearance', [AppearanceController::class, 'setAppearance'])->name('settings.appearance.appearance/add');
    Route::get('/appearance/{location_id}', [AppearanceController::class, 'getAppearance'])->name('settings.appearance.appearance/view');

    // GET payment for one location
    Route::get('/payments/{location_id}', [PaymentMethodController::class, 'getPayments'])->name('pos.payment.payment/view');
    // Add Payment Method
    Route::POST('/payments', [PaymentMethodController::class, 'addPayment'])->name('pos.payment.payment/add');
    Route::PUT('/payments/{id}',[PaymentMethodController::class, 'updatePayment'])->name('pos.payment.payment/update');
    Route::delete('/payments/{id}', [PaymentMethodController::class, 'deletePayment'])->name('pos.payment.payment/delete');

    // Get Pricing group
    Route::get('/pricing-group/{location_id}', [PricingGroupController::class, 'getPricingGroup'])->name('pos.pricingGroup.pricingGroup/view');
    Route::post('/pricing-group', [PricingGroupController::class, 'addPricingGroup'])->name('pos.pricingGroup.pricingGroup/add');
    Route::put('/pricing-group/{id}', [PricingGroupController::class, 'updatePricingGroup'])->name('pos.pricingGroup.pricingGroup/update');
    Route::delete('/pricing-group/{id}', [PricingGroupController::class, 'deletePricingGroup'])->name('pos.pricingGroup.pricingGroup/delete');

    // getQuotationsList
    Route::get('/quotations-list', [QuotationsListController::class, 'getQuotationsList'])->name('sales.quotations-list.quotations-list/view');
    Route::get('/quotations-list/{id}', [QuotationsListController::class, 'getQuotationList'])->name('sales.quotations-list.quotations-list/get-by-id');
    //Add Pricing Group
    Route::post('/quotations-list', [QuotationsListController::class, 'addQuotationsList'])->name('sales.quotations-list.quotations-list/add');
    Route::put('/quotations-list/{id}', [QuotationsListController::class, 'updateQuotationsList'])->name('sales.quotations-list.quotations-list/update');
    Route::delete('/quotations-list/{id}', [QuotationsListController::class, 'deleteQuotationsList'])->name('sales.quotations-list.quotations-list/delete');

    //Setup Pricing Group
    //    Route::post('/setQuotationsList', [QuotationsListController::class, 'setQuotationsList'])->name('SetQuotationsList');

    // Suppliers
    Route::get('/suppliers/{location_id}', [SupplierController::class, 'index'])->name('suppliers.suppliers/view');
    Route::get('/suppliers/{id}/show', [SupplierController::class, 'show'])->name('suppliers.suppliers/show');
    Route::post('/suppliers/{location_id}', [SupplierController::class, 'store'])->name('suppliers.suppliers/add');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.suppliers/update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.suppliers/delete');

    Route::post('/send-products', [DigitalMenuController::class, 'sendProducts'])->name('digitalMenu.sendProducts/add');

});

require __DIR__ . '/auth.php';
require __DIR__ . '/api/business.php';
require __DIR__ . '/api/report.php';
require __DIR__ . '/api/sales.php';

require __DIR__ . '/api/permissions.php';
require __DIR__ . '/api/printSettings.php';
