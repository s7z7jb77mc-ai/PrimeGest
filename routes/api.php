<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\LegacySyncController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// ── Health check (utilisé par SyncWorker pour détecter la connectivité) ──
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ── Statut de sync local (interrogé par le store Pinia) ──
Route::middleware('auth:sanctum')->get('/local/sync-status', function () {
    $pending = \App\Models\SyncQueue::where('status', 'pending')->count();
    $syncing = \App\Models\SyncQueue::where('status', 'syncing')->count();
    $lastDone = \App\Models\SyncQueue::where('status', 'done')
        ->orderByDesc('synced_at')
        ->value('synced_at');

    return response()->json([
        'pending_count' => $pending,
        'is_syncing' => $syncing > 0,
        'last_sync_at' => $lastDone,
    ]);
});

// ── Flux officiel offline-first : SQLite locale -> SyncWorker -> cloud ──
Route::middleware('auth:sanctum')->post('/v1/sync', [SyncController::class, 'receive']);

Route::post('auth/token', [AuthTokenController::class, 'issue'])->middleware('throttle:10,1');
Route::middleware('auth:sanctum')
    ->delete('auth/token', [AuthTokenController::class, 'revoke']);
// Émet un token Sanctum depuis une session web active (utilisé par Tauri après login Inertia)
Route::middleware('auth:web')
    ->post('auth/tauri-token', [AuthTokenController::class, 'issueTauri']);

// ── Flux legacy web/Tauri conservé tant que le frontend n'est pas migré vers v1/sync ──
Route::middleware('auth:sanctum')
    ->prefix('sync')
    ->name('sync.')
    ->group(function () {
        Route::post('push', [LegacySyncController::class, 'push'])->name('push');
        Route::get('pull', [LegacySyncController::class, 'pull'])->name('pull');
        Route::get('status', [LegacySyncController::class, 'status'])->name('status');
    });

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
