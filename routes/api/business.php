<?php

use App\Http\Controllers\BusinessController;
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

Route::group(['as' => 'api.', 'prefix' => 'business', 'middleware' => ['jwt','checkPermissions']], function () {
    Route::controller(BusinessController::class)->group(function () {
        Route::group(['prefix' => 'locations'], function () {
            Route::get('/', 'getBusinessLocations')->name('business.locations.locations/view');
            Route::get('/{id}', 'showBusinessLocation')->name('business.locations.locations/show');
            Route::put('/{id}', 'updateBusinessLocation')->name('business.locations.locations/update')->middleware('isOwner');
            Route::post('/', 'storeBusinessLocation')->name('business.locations.locations/add')->middleware('isOwner');
            Route::put('/print-type/{id}', 'updatePrintType')->name('business.locations.locations/update-print-type')->middleware('isOwner');
            Route::delete('/{id}', 'destroyBusinessLocation')->name('business.locations.locations/destroy')->middleware('isOwner');
        });
        Route::get('/types', 'getTypes')->name('business.types.locations/view')->middleware('isOwner');

        Route::get('/', 'index')->name('business.business/view');
        Route::get('/{id}', 'show')->name('business.business/show')->middleware('isOwner');
        Route::put('/{id}', 'update')->name('business.business/update')->middleware('isOwner');
        Route::post('/', 'store')->name('business.business/add')->middleware('isOwner');
        Route::delete('/{id}', 'destroy')->name('business.business/destroy')->middleware('isOwner');

    });
});
