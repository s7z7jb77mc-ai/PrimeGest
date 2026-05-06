# SKILL : Optimisation PrimeGest
# Lire avant toute optimisation performance, cache ou frontend.
# Référence complète : .claude/references/REF_OPTIMISATION.md

## 1. BASE DE DONNÉES — RÈGLES ESSENTIELLES

### Index obligatoires sur chaque table métier
```php
$table->index(['entreprise_id', 'succursale_id']); // filtre multi-tenant
$table->index(['uuid']);                            // lookup API
$table->index(['synced', 'device_id']);            // sync offline
$table->index(['statut', 'entreprise_id']);        // filtres courants
$table->index(['created_at']);                      // tri chronologique
$table->index(['updated_at']);                      // conflits sync
```

### N+1 — zéro tolérance
```php
// ✗ Mauvais
$ventes = Vente::all();
foreach ($ventes as $v) { echo $v->tiers->nom; } // N+1 !

// ✓ Bon
$ventes = Vente::with(['tiers', 'enntreprise', 'succursale', 'lignes.produit'])->paginate(15);
```

### Agrégations SQL plutôt que PHP
```php
// ✗ Mauvais — charge tout en mémoire
$total = Vente::all()->sum('montant_ttc');

// ✓ Bon — calcul en base
$stats = Vente::where('entreprise_id', $id)
    ->selectRaw('SUM(montant_ttc) as total, COUNT(*) as nombre')
    ->first();
```

### SQLite — pragmas offline (AppServiceProvider::boot)
```php
if (config('database.default') === 'sqlite') {
    $pdo = DB::connection()->getPdo();
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA synchronous=NORMAL;');
    $pdo->exec('PRAGMA cache_size=10000;');
    $pdo->exec('PRAGMA temp_store=MEMORY;');
}
```

## 2. CACHE — STRATÉGIE

| Type de données              | TTL recommandé  |
|------------------------------|-----------------|
| Paramètres entreprise        | Permanent       |
| Limites plan Freemium        | 1 heure         |
| Liste produits               | 30 minutes      |
| Dashboard                    | 5 minutes       |
| Solde caisse                 | Pas de cache    |

```php
// Pattern standard
$data = Cache::remember(
    key:      "produits.{$entrepriseId}.{$succursaleId}",
    ttl:      now()->addMinutes(30),
    callback: fn() => Produit::actif()->where('entreprise_id', $entrepriseId)->get()
);

// Invalider après modification
Cache::forget("produits.{$entrepriseId}.{$succursaleId}");
```

## 3. FRONTEND VUE.JS

```typescript
// Lazy loading des pages (toutes sauf dashboard)
{ path: '/ventes', component: () => import('@/pages/VentesPage.vue') }

// Debounce sur les recherches
const debouncedSearch = useDebounceFn(async (q: string) => {
    if (q.length < 2) return
    await store.search(q)
}, 300)

// computed() pour éviter les recalculs inutiles
const total = computed(() =>
    ventes.value.reduce((sum, v) => sum + v.montant_ttc, 0)
)
// ✗ Jamais une fonction ordinaire pour un calcul répété
```

## 4. SYNC_QUEUE — MAINTENANCE

```php
// Nettoyage hebdomadaire automatique (routes/console.php)
Schedule::command('primegest:clean-sync')->weekly();

// La commande supprime les entrées done > 7 jours
// et archive les conflits > 30 jours
```

## 5. PRODUCTION — COMMANDES OBLIGATOIRES

```bash
php artisan config:cache      # cacher la config
php artisan route:cache       # cacher les routes
php artisan view:cache        # cacher les vues
composer install --optimize-autoloader --no-dev
npm run build
```

## 6. MÉTRIQUES À SURVEILLER

```
Requête SQL > 100ms  → ajouter un index
N+1 détecté          → ajouter with()
Cache hit < 70%      → revoir la stratégie
sync_queue > 1000    → augmenter batch size
Page > 3s            → lazy loading + bundle Vite
```

## 7. RÈGLES ABSOLUES

```
✓ Index sur toutes les colonnes WHERE / ORDER BY / GROUP BY
✓ with() systématique — Model::preventLazyLoading() en local
✓ Cache sur les données semi-statiques
✓ Pagination — jamais ->get() sur les listes API
✓ Agrégation SQL pour les calculs dashboard
✓ Lazy loading composants Vue.js sauf page d'accueil
✓ Debounce 300ms minimum sur les recherches
✓ config:cache + route:cache en production

✗ Jamais SELECT * sur grandes tables (sélectionner colonnes)
✗ Jamais ->all() sans limit
✗ Jamais calcul PHP sur ce qu'on peut faire en SQL
✗ Jamais Telescope en production
✗ Jamais APP_DEBUG=true en production
```

Stratégies complètes et exemples → voir REF_OPTIMISATION.md
