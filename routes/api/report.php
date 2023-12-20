<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

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
//
Route::group(['as' => 'api.', 'prefix' => 'reports', 'middleware' => ['jwt', 'checkPermissions']], function () {
    Route::controller(ReportController::class)->group(function () {
        Route::group(['as' => 'reports.'], function () {
            Route::get('/register/{location_id}', 'getRegistrationReport')->name('reports.open-close');
            Route::get('/latest-register/{location_id}', 'latestRegistrationReport')->name('reports.latest-open-close');
            Route::get('/sales/{location_id}/{order_id?}', 'getSalesReport')->name('reports.sales');
            Route::get('/item-sales/{location_id}/{order_id?}', 'getItemSalesReport')->name('reports.item-sales');
            Route::get('/purchase/{location_id}', 'getPurchaseReport')->name('reports.purchase');

            // Get Stock Item
            Route::get('/itemStock', 'getItemStockReport')->name('reports.stock');

            // Get Item From All Supplier
            Route::get('/items/{location_id}', 'getItemItemReport')->name('reports.items');
        });
    });
});
