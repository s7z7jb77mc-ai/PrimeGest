# RÉFÉRENCE : Optimisation PrimeGest
# Consulté par Claude Code pour les détails.
# Skill résumé : .claude/skills/SKILL_OPTIMISATION.md

## 1. INDEX — STRATÉGIE COMPLÈTE

```php
// Index standards sur toutes les tables métier
$table->index(['entreprise_id', 'succursale_id']);          // filtre principal
$table->index(['uuid']);                                      // lookup API
$table->index(['synced', 'device_id']);                     // sync offline
$table->index(['statut', 'entreprise_id']);                 // filtres courants
$table->index(['created_at']);                               // tri chrono
$table->index(['updated_at']);                               // conflits sync

// Index composites pour requêtes dashboard fréquentes
$table->index(['entreprise_id', 'statut', 'created_at']);   // ventes filtrées
$table->index(['succursale_id', 'vendu_at']);               // rapport succursale

// Index uniques
$table->unique(['entreprise_id', 'reference']);              // unicité référence
$table->unique(['uuid']);                                     // UUID global

// Index sur sync_queue (requête très fréquente)
// Dans la migration de sync_queue :
$table->index(['status', 'attempts']);       // lecture pending
$table->index(['table_name', 'record_uuid']); // recherche entrée spécifique
$table->index(['created_at']);               // ordre chronologique
```

## 2. CORRECTION N+1 — CAS RÉELS PRIMEGEST

```php
// Ventes avec toutes les relations
Vente::with([
    'tiers',
    'succursale',
    'user:id,nom,prenom',
    'lignes',
    'lignes.produit:id,nom,uuid,prix_vente',
])->paginate(15);

// Dashboard — stats par succursale
Succursale::withCount(['ventes', 'employes', 'produits'])
    ->with(['manager:id,nom,prenom'])
    ->where('entreprise_id', $id)
    ->get();

// Produits en rupture avec leur succursale
Produit::with('succursale:id,nom')
    ->whereColumn('quantite', '<=', 'seuil_alerte')
    ->where('statut', 'actif')
    ->select(['uuid', 'nom', 'quantite', 'seuil_alerte', 'succursale_id'])
    ->get();

// Dettes en retard
Dette::with(['tiers:id,uuid,nom,telephone', 'succursale:id,nom'])
    ->where('statut', 'retard')
    ->where('entreprise_id', $id)
    ->orderBy('echeance')
    ->paginate(15);
```

## 3. CACHE — IMPLÉMENTATION COMPLÈTE

```php
// app/Services/CacheService.php
class CacheService
{
    // Clés de cache standardisées
    public static function keyProduits(int $entrepriseId, int $succursaleId): string
    {
        return "produits.{$entrepriseId}.{$succursaleId}";
    }

    public static function keyDashboard(int $entrepriseId, ?int $succursaleId): string
    {
        return "dashboard.{$entrepriseId}." . ($succursaleId ?? 'global');
    }

    public static function keyPlan(int $entrepriseId): string
    {
        return "plan.{$entrepriseId}";
    }

    public static function keyParametres(int $entrepriseId): string
    {
        return "parametres.{$entrepriseId}";
    }

    // Invalider tout le cache d'une entreprise
    public static function flushEntreprise(int $entrepriseId): void
    {
        Cache::tags(["entreprise.{$entrepriseId}"])->flush();
        // Ou manuellement :
        Cache::forget(self::keyPlan($entrepriseId));
        Cache::forget(self::keyParametres($entrepriseId));
        // Les clés produits et dashboard expirent naturellement
    }
}

// Utilisation dans les contrôleurs
$produits = Cache::remember(
    CacheService::keyProduits($user->entreprise_id, $user->succursale_id),
    now()->addMinutes(30),
    fn() => Produit::actif()
        ->where('entreprise_id', $user->entreprise_id)
        ->where('succursale_id', $user->succursale_id)
        ->select(['uuid', 'nom', 'prix_vente', 'quantite', 'seuil_alerte'])
        ->get()
);

// Invalider après modification d'un produit
// Dans ProduitObserver ou l'Action CreerProduitAction :
Cache::forget(CacheService::keyProduits($produit->entreprise_id, $produit->succursale_id));
```

## 4. DASHBOARD — REQUÊTES OPTIMISÉES

```php
// app/Actions/Dashboard/GetDashboardStatsAction.php
class GetDashboardStatsAction
{
    public function __invoke(User $user): array
    {
        $entrepriseId  = $user->entreprise_id;
        $succursaleId  = $user->succursale_id;
        $isSuperAdmin  = $user->role === 'super_admin';
        $debutMois     = now()->startOfMonth();
        $finMois       = now()->endOfMonth();

        return Cache::remember(
            "dashboard.{$entrepriseId}." . ($isSuperAdmin ? 'global' : $succursaleId),
            now()->addMinutes(5),
            function () use ($entrepriseId, $succursaleId, $isSuperAdmin, $debutMois, $finMois) {

                $query = Vente::where('entreprise_id', $entrepriseId)
                    ->where('statut', 'paye')
                    ->whereBetween('vendu_at', [$debutMois, $finMois]);

                if (!$isSuperAdmin) {
                    $query->where('succursale_id', $succursaleId);
                }

                $stats = $query->selectRaw(
                    'SUM(montant_ttc) as total_ventes,
                     COUNT(*) as nombre_ventes,
                     AVG(montant_ttc) as vente_moyenne'
                )->first();

                $totalStock = Produit::where('entreprise_id', $entrepriseId)
                    ->when(!$isSuperAdmin, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->selectRaw('SUM(quantite * prix_achat) as valeur_stock')
                    ->value('valeur_stock') ?? 0;

                $alertesStock = Produit::where('entreprise_id', $entrepriseId)
                    ->when(!$isSuperAdmin, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->whereColumn('quantite', '<=', 'seuil_alerte')
                    ->where('statut', 'actif')
                    ->with('succursale:id,nom')
                    ->select(['uuid', 'nom', 'quantite', 'seuil_alerte', 'succursale_id'])
                    ->get();

                $topProduits = DB::table('vente_lignes')
                    ->join('ventes',   'vente_lignes.vente_id',   '=', 'ventes.id')
                    ->join('produits', 'vente_lignes.produit_id', '=', 'produits.id')
                    ->where('ventes.entreprise_id', $entrepriseId)
                    ->when(!$isSuperAdmin, fn($q) => $q->where('ventes.succursale_id', $succursaleId))
                    ->where('ventes.statut', 'paye')
                    ->whereBetween('ventes.vendu_at', [$debutMois, $finMois])
                    ->selectRaw('produits.nom, produits.uuid,
                                 SUM(vente_lignes.quantite) as quantite_vendue,
                                 SUM(vente_lignes.total_ligne) as chiffre_affaires')
                    ->groupBy('produits.id', 'produits.nom', 'produits.uuid')
                    ->orderByDesc('quantite_vendue')
                    ->limit(10)
                    ->get();

                return compact('stats', 'totalStock', 'alertesStock', 'topProduits');
            }
        );
    }
}
```

## 5. OPTIMISATION VITE — CONFIG COMPLÈTE

```typescript
// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue':    ['vue', 'vue-router', 'pinia'],
                    'vendor-ui':     ['@headlessui/vue'],
                    'vendor-charts': ['chart.js', 'vue-chartjs'],
                    'vendor-utils':  ['axios', 'dayjs'],
                }
            }
        },
        chunkSizeWarningLimit: 600,
    },
    resolve: {
        alias: { '@': '/resources/js' }
    }
})
```

## 6. VIRTUALISATION DES LONGUES LISTES

```vue
<!-- Pour les listes > 100 éléments (ex: liste produits) -->
<!-- npm install vue-virtual-scroller -->
<template>
  <RecycleScroller
    class="h-screen"
    :items="produits"
    :item-size="72"
    key-field="uuid"
    v-slot="{ item }"
  >
    <ProduitCard :produit="item" />
  </RecycleScroller>
</template>

<script setup lang="ts">
import { RecycleScroller } from 'vue-virtual-scroller'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
</script>
```

## 7. MAINTENANCE SYNC_QUEUE

```php
// app/Console/Commands/CleanSyncQueue.php
class CleanSyncQueue extends Command
{
    protected $signature   = 'primegest:clean-sync';
    protected $description = 'Nettoyer les entrées sync_queue traitées';

    public function handle(): void
    {
        $done = SyncQueue::where('status', 'done')
            ->where('synced_at', '<', now()->subDays(7))
            ->delete();

        $archived = SyncQueue::where('status', 'conflict')
            ->where('created_at', '<', now()->subDays(30))
            ->update(['status' => 'archived']);

        $this->info("✓ {$done} entrées supprimées, {$archived} conflits archivés");
    }
}

// routes/console.php (Laravel 11)
Schedule::command('primegest:clean-sync')->weekly();
Schedule::command('primegest:check-plans')->daily(); // vérifier abonnements expirés
```

## 8. MONITORING — CONFIGURATION SENTRY

```php
// config/sentry.php
return [
    'dsn'                  => env('SENTRY_LARAVEL_DSN'),
    'traces_sample_rate'   => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => 0.1,
    'send_default_pii'     => false,
    'ignore_exceptions'    => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    ],
];
```

## 9. NGINX — COMPRESSION ET CACHE (Contabo)

```nginx
# /etc/nginx/sites-available/primegest.app
server {
    # Compression gzip
    gzip on;
    gzip_types application/json text/plain text/css
               application/javascript application/xml;
    gzip_min_length 256;
    gzip_comp_level 6;

    # Cache fichiers statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Pas de cache pour l'API
    location /api/ {
        add_header Cache-Control "no-store, no-cache";
    }
}
```
