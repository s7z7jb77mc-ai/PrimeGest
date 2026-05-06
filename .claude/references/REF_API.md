# RÉFÉRENCE : API REST PrimeGest
# Consulté par Claude Code pour les détails.
# Skill résumé : .claude/skills/SKILL_API.md

## 1. HELPER ApiResponse — CODE COMPLET

```php
// app/Helpers/ApiResponse.php
namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed  $data    = null,
        string $message = 'Opération réussie',
        int    $status  = 200
    ): JsonResponse {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    public static function created(mixed $data, string $message = 'Créé avec succès'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function paginated(mixed $paginator, mixed $resource): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $resource,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    public static function error(
        string $message,
        int    $status  = 400,
        string $code    = '',
        array  $data    = [],
        array  $errors  = []
    ): JsonResponse {
        $response = ['success' => false, 'message' => $message];
        if ($code)   $response['code']   = $code;
        if ($data)   $response['data']   = $data;
        if ($errors) $response['errors'] = $errors;
        return response()->json($response, $status);
    }

    public static function notFound(string $message = 'Ressource introuvable'): JsonResponse
    {
        return self::error($message, 404, 'NOT_FOUND');
    }

    public static function forbidden(string $message, string $code = 'FORBIDDEN'): JsonResponse
    {
        return self::error($message, 403, $code);
    }
}
```

## 2. TOUTES LES ROUTES API

```php
// routes/api.php

// Santé (sans auth)
Route::get('/health', fn() => response()->json([
    'status' => 'ok', 'timestamp' => now()->toIso8601String()
]));

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/login',  [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me',      [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Sync offline→cloud
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/sync',        [SyncController::class, 'receive']);
    Route::get('/v1/sync/status',  [SyncController::class, 'status']);
});

// API principale
Route::prefix('v1')
     ->middleware(['auth:sanctum', 'entreprise', 'succursale.scope'])
     ->group(function () {

    // Dashboard
    Route::get('/dashboard',           [DashboardController::class, 'index']);
    Route::get('/dashboard/succursales',[DashboardController::class, 'succursales'])
         ->middleware('plan.limit:succursales');

    // Produits
    Route::apiResource('produits', ProduitController::class)
         ->parameters(['produits' => 'uuid'])
         ->middleware(['plan.limit:produits']);
    Route::post('/produits/{uuid}/ajuster-stock', [ProduitController::class, 'ajusterStock']);

    // Ventes
    Route::apiResource('ventes', VenteController::class)
         ->parameters(['ventes' => 'uuid']);
    Route::post('/ventes/{uuid}/annuler', [VenteController::class, 'annuler']);

    // Caisse
    Route::get('/caisse',             [CaisseController::class, 'index']);
    Route::post('/caisse',            [CaisseController::class, 'store']);
    Route::post('/caisse/transfert',  [CaisseController::class, 'transfert'])
         ->middleware('plan.limit:succursales');

    // Mouvements de stock
    Route::get('/mouvements',             [MouvementController::class, 'index']);
    Route::post('/mouvements/entree',     [MouvementController::class, 'entree']);
    Route::post('/mouvements/sortie',     [MouvementController::class, 'sortie']);
    Route::post('/mouvements/transfert',  [MouvementController::class, 'transfert'])
         ->middleware('plan.limit:succursales');

    // Tiers (clients + fournisseurs fusionnés)
    Route::apiResource('tiers', TiersController::class)
         ->parameters(['tiers' => 'uuid'])
         ->middleware('plan.limit:tiers');

    // Dettes et créances (Premium+)
    Route::apiResource('dettes', DetteController::class)
         ->parameters(['dettes' => 'uuid'])
         ->middleware('plan.limit:dettes');
    Route::post('/dettes/{uuid}/payer', [DetteController::class, 'payer'])
         ->middleware('plan.limit:dettes');

    // Employés
    Route::apiResource('employes', EmployeController::class)
         ->parameters(['employes' => 'uuid']);

    // Fiches de paie
    Route::apiResource('fiches-paie', FichesPaieController::class)
         ->parameters(['fiches-paie' => 'uuid']);

    // Succursales (plan Succursales uniquement)
    Route::apiResource('succursales', SucursaleController::class)
         ->parameters(['succursales' => 'uuid'])
         ->middleware('plan.limit:succursales');

    // Transferts inter-succursales
    Route::apiResource('transfers', TransferController::class)
         ->parameters(['transfers' => 'uuid'])
         ->middleware('plan.limit:succursales');
    Route::post('/transfers/{uuid}/confirmer', [TransferController::class, 'confirmer'])
         ->middleware('plan.limit:succursales');

    // Journal
    Route::get('/journal', [JournalController::class, 'index']);

    // Rapports (Premium+)
    Route::prefix('rapports')->middleware('plan.limit:rapports')->group(function () {
        Route::get('/ventes',   [RapportController::class, 'ventes']);
        Route::get('/stock',    [RapportController::class, 'stock']);
        Route::get('/caisse',   [RapportController::class, 'caisse']);
        Route::get('/employes', [RapportController::class, 'employes']);
    });

    // Export PDF/Excel (Premium+)
    Route::prefix('export')->middleware('plan.limit:export_pdf')->group(function () {
        Route::get('/facture/{uuid}',    [ExportController::class, 'facture']);
        Route::get('/rapport/{type}',    [ExportController::class, 'rapport']);
        Route::get('/fiche-paie/{uuid}', [ExportController::class, 'fichePaie']);
    });

    // Chat interne (Premium+)
    Route::prefix('chat')->middleware('plan.limit:chat')->group(function () {
        Route::get('/messages',  [ChatController::class, 'index']);
        Route::post('/messages', [ChatController::class, 'store']);
        Route::get('/groupes',   [ChatController::class, 'groupes']);
    });

    // Tâches (Premium+)
    Route::apiResource('taches', TacheController::class)
         ->parameters(['taches' => 'uuid'])
         ->middleware('plan.limit:chat');

    // Utilisateurs
    Route::apiResource('users', UserController::class)
         ->parameters(['users' => 'uuid'])
         ->middleware('plan.limit:users');

    // Paramètres entreprise
    Route::get('/parametres', [ParametreController::class, 'index']);
    Route::put('/parametres', [ParametreController::class, 'update']);

    // Archives
    Route::prefix('archives')->group(function () {
        Route::get('/ventes',  [ArchiveController::class, 'ventes']);
        Route::get('/stock',   [ArchiveController::class, 'stock']);
        Route::get('/journal', [ArchiveController::class, 'journal']);
    });

    // Plan / abonnement
    Route::get('/plan',          [PlanController::class, 'index']);
    Route::post('/plan/upgrade', [PlanController::class, 'upgrade']);
});
```

## 3. MIDDLEWARE COMPLETS

### CheckPlanLimit

```php
// app/Http/Middleware/CheckPlanLimit.php
class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $entreprise = $request->user()->entreprise;
        $limits     = config("plan_limits.{$entreprise->plan}");

        // Feature booléenne
        if (isset($limits[$feature]) && $limits[$feature] === false) {
            return ApiResponse::forbidden(
                "Fonctionnalité non disponible dans votre plan actuel.",
                'PLAN_LIMIT_REACHED'
            );
        }

        // Feature avec quota
        if (isset($limits[$feature]) && is_int($limits[$feature])) {
            $current = $entreprise->countUsage($feature);
            if ($current >= $limits[$feature]) {
                return ApiResponse::error(
                    "Limite de {$feature} atteinte. Passez au plan supérieur.",
                    403, 'PLAN_LIMIT_REACHED',
                    ['limit' => $limits[$feature], 'current' => $current, 'plan' => $entreprise->plan]
                );
            }
        }

        return $next($request);
    }
}
```

### SucursaleScope

```php
// app/Http/Middleware/SucursaleScope.php
class SucursaleScope
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if ($user && in_array($user->role, ['manager', 'vendeur', 'caissier'])) {
            $request->merge(['succursale_id' => $user->succursale_id]);
        }
        return $next($request);
    }
}
```

### Enregistrement

```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'plan.limit'       => CheckPlanLimit::class,
        'succursale.scope' => SucursaleScope::class,
        'entreprise'       => EntrepriseScope::class,
    ]);
})
```

## 4. CONFIG PLAN LIMITS

```php
// config/plan_limits.php
return [
    'free' => [
        'produits'     => 100,
        'tiers'        => 15,   // clients + fournisseurs confondus
        'users'        => 1,
        'dettes'       => false,
        'export_pdf'   => false,
        'chat'         => false,
        'rapports'     => false,
        'succursales'  => false,
        'storage_mb'   => 500,
    ],
    'premium' => [
        'produits'     => 'unlimited',
        'tiers'        => 'unlimited',
        'users'        => 10,
        'dettes'       => true,
        'export_pdf'   => true,
        'chat'         => true,
        'rapports'     => true,
        'succursales'  => false,
        'storage_mb'   => 5120,
    ],
    'succursales' => [
        'produits'     => 'unlimited',
        'tiers'        => 'unlimited',
        'users'        => 20,
        'dettes'       => true,
        'export_pdf'   => true,
        'chat'         => true,
        'rapports'     => true,
        'succursales'  => 'unlimited',
        'storage_mb'   => 10240,
    ],
];
```

## 5. RATE LIMITING

```php
// bootstrap/app.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
RateLimiter::for('sync', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->entreprise_id ?: $request->ip());
});
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```
