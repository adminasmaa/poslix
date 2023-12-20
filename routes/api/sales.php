<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
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
Route::group(['as' => 'api.', 'prefix' => 'sales', 'middleware' => ['jwt', 'checkPermissions']], function () {
    Route::controller(SalesController::class)->group(function () {
        Route::get('/{order_id}', 'index')->name('sales.sales-list.sales-list/view');
        Route::put('/{order_id}', 'update')->name('sales.sales-list.sales-list/update');
        Route::delete('/{order_id}', 'destroy')->name('sales.sales-list.sales-list/destroy');
        Route::put('/complete-payment/{order_id}', 'completePayment')->name('sales.sales-list.sales-list/complete-payment');
    });
});
