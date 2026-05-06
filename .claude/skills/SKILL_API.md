# SKILL : API REST PrimeGest
# Lire avant tout contrôleur, route ou Resource.
# Référence complète : .claude/references/REF_API.md

## 1. FORMAT DE RÉPONSE — STANDARD UNIQUE

```php
// Succès
return ApiResponse::success(VenteResource::make($vente), 'Vente créée', 201);

// Liste paginée
return ApiResponse::paginated($ventes, VenteResource::collection($ventes));

// Erreur
return ApiResponse::error('Limite atteinte.', 403, 'PLAN_LIMIT_REACHED', [
    'limit' => 15, 'current' => 15, 'plan' => 'free',
]);
```

## 2. RÈGLES DE NOMMAGE DES ROUTES

```
GET    /api/v1/ventes           → liste paginée
POST   /api/v1/ventes           → créer
GET    /api/v1/ventes/{uuid}    → détail  ← uuid JAMAIS id
PUT    /api/v1/ventes/{uuid}    → modifier
DELETE /api/v1/ventes/{uuid}    → supprimer
```

## 3. MIDDLEWARE STACK — OBLIGATOIRE SUR TOUTES LES ROUTES V1

```php
Route::prefix('v1')
     ->middleware(['auth:sanctum', 'entreprise', 'succursale.scope'])
     ->group(function () {
         // Routes soumises au Freemium
         Route::post('/tiers', [TiersController::class, 'store'])
              ->middleware('plan.limit:tiers');
         // Routes succursales uniquement
         Route::apiResource('succursales', SucursaleController::class)
              ->middleware('plan.limit:succursales');
     });
```

## 4. TEMPLATE DE CONTRÔLEUR

```php
class VenteController extends Controller
{
    public function __construct(
        private readonly CreerVenteAction $creerVente,
    ) {}

    public function index(): JsonResponse
    {
        $ventes = Vente::with(['tiers', 'lignes.produit', 'succursale'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return ApiResponse::paginated($ventes, VenteResource::collection($ventes));
    }

    public function store(CreerVenteRequest $request): JsonResponse
    {
        $vente = ($this->creerVente)($request->validated());
        return ApiResponse::success(VenteResource::make($vente), 'Vente créée', 201);
    }
}
// Zéro logique dans le contrôleur — déléguer aux Actions
```

## 5. TEMPLATE D'ACTION

```php
class CreerVenteAction
{
    public function __invoke(array $data): Vente
    {
        return DB::transaction(function () use ($data) {
            $vente = Vente::create([
                'uuid'          => Str::uuid(),
                'entreprise_id' => auth()->user()->entreprise_id,
                'succursale_id' => auth()->user()->succursale_id,
                // ... données métier
            ]);
            // lignes, stock, caisse dans la même transaction
            return $vente;
        });
    }
}
```

## 6. CODES HTTP DE RÉFÉRENCE

| Code | Usage                                    |
|------|------------------------------------------|
| 200  | Succès lecture / mise à jour             |
| 201  | Créé avec succès                         |
| 204  | Supprimé (sans contenu)                  |
| 403  | Plan insuffisant / accès succursale      |
| 404  | Ressource introuvable                    |
| 422  | Erreur de validation                     |
| 429  | Trop de requêtes (rate limiting)         |

## 7. CODES D'ERREUR MÉTIER STANDARDISÉS

```
PLAN_LIMIT_REACHED       → limite Freemium atteinte
SUCCURSALE_ACCESS_DENIED → accès à une autre succursale refusé
INSUFFICIENT_STOCK       → stock insuffisant
TRANSFER_AUTH_FAILED     → mot de passe confirmation invalide
SUBSCRIPTION_EXPIRED     → abonnement expiré
```

## 8. RÈGLES ABSOLUES

```
✓ uuid dans toutes les URLs — jamais l'id
✓ Toujours ApiResponse helper — jamais response()->json() direct
✓ Toujours API Resources — jamais ->toArray() direct
✓ Toujours Form Requests — jamais $request->all()
✓ Toujours DB::transaction() pour multi-tables
✓ middleware plan.limit sur routes Freemium
✓ middleware succursale.scope sur toutes les routes v1

✗ Jamais de logique dans les contrôleurs
✗ Jamais retourner l'id dans les réponses
✗ Jamais de route sans versionnement /v1/
```

Toutes les routes détaillées → voir REF_API.md
