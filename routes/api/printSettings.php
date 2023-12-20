<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PrintSettingController;
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

Route::group(['as' => 'api.', 'prefix' => 'print-settings', 'middleware' => ['jwt','checkPermissions']], function () {
//    Route::get('/', [BusinessController::class, 'index']);
    Route::post('/', [PrintSettingController::class, 'store'])->name('settings.appearance.appearance/add');
    Route::get('/{id}', [PrintSettingController::class, 'show'])->name('settings.appearance.appearance/view');
    Route::get('/showAll/{location_id}', [PrintSettingController::class, 'showAll'])->name('settings.appearance.appearance/viewAll');
    Route::put('/{id}', [PrintSettingController::class, 'update'])->name('settings.appearance.appearance/update');
    Route::delete('/{id}', [PrintSettingController::class, 'destroy'])->name('settings.appearance.appearance/delete');
//    Route::delete('/{id}', [PrintSettingController::class, 'destroy']);
});
