<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\NewPermissionController;
use App\Models\NewPermission;
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

Route::group(['as' => 'api.', 'prefix' => 'new-permissions'], function () {
    Route::get('permissions', [NewPermissionController::class, 'index']);

    Route::get('get-permissions', [NewPermissionController::class, 'getPermissions']);
});

Route::group(['as' => 'api.', 'prefix' => 'roles', 'middleware' => 'jwt'], function () {
    Route::post('store', [NewPermissionController::class, 'storeRole'])->name('roles.roles/add');
    Route::get('get', [NewPermissionController::class, 'getRoles'])->name('roles.roles/view');
    Route::delete('delete/{id}', [NewPermissionController::class, 'deleteRole'])->name('roles.roles/delete');
    Route::put('update/{id}', [NewPermissionController::class, 'updateRole'])->name('roles.roles/update');

    Route::post('assign', [NewPermissionController::class, 'assignRole'])->name('roles.roles/assign');
    Route::post('deassign', [NewPermissionController::class, 'deassignRole'])->name('roles.roles/design');
});
