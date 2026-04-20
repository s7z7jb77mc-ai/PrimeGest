<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\SyncController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('auth/token',  [AuthTokenController::class, 'issue']);
Route::middleware('auth:sanctum')
    ->delete('auth/token', [AuthTokenController::class, 'revoke']);

Route::middleware('auth:sanctum')
    ->prefix('sync')
    ->name('sync.')
    ->group(function () {
        Route::post('push',   [SyncController::class, 'push'])->name('push');
        Route::get('pull',    [SyncController::class, 'pull'])->name('pull');
        Route::get('status',  [SyncController::class, 'status'])->name('status');
    });

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
