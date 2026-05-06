# PrimeGest — Instructions pour Claude Code
# ═══════════════════════════════════════════════════════════════════
# Ce fichier est lu automatiquement par Claude Code à chaque session.
# Respecter toutes les règles sans exception.
# En cas de doute sur une convention, demander avant de coder.
# ═══════════════════════════════════════════════════════════════════

## 1. PRÉSENTATION DU PROJET

PrimeGest est une application web de gestion commerciale destinée
aux petites et moyennes entreprises d'Afrique Centrale (RDC).

### Fonctionnalités principales
- Livre de caisse et mouvements de stock
- Gestion des produits, clients, fournisseurs (page Tiers)
- Génération de factures PDF
- Fiches de paie
- Gestion des succursales (multi-établissements)
- Transferts inter-succursales (argent et stock)
- Chat interne et gestion des tâches
- Système Freemium (Free / Premium 7$ / Succursales 10$)
- Mode offline-first avec synchronisation cloud automatique

### Hébergement et infrastructure
- Serveur  : VPS Contabo Ubuntu 22.04 (4.98 USD/mois)
- Domaine  : primegest.app (Cloudflare)
- SSL      : Let's Encrypt via Certbot
- CDN      : Cloudflare (proxy activé)
- Fichiers : Cloudflare R2 (compatible S3)
- Emails   : Resend (noreply@primegest.app)
- Monitoring : Sentry


## 2. STACK TECHNOLOGIQUE

| Couche       | Technologie              | Version  |
|--------------|--------------------------|----------|
| Backend      | Laravel                  | 12.x     |
| Langage      | PHP                      | 8.2+     |
| Frontend     | Vue.js (Composition API) | 3.x      |
| State        | Pinia                    | 2.x      |
| CSS          | Tailwind CSS             | 3.x      |
| Build        | Vite                     | 5.x      |
| BDD locale   | SQLite 3                 | —        |
| BDD cloud    | MySQL                    | 8.x      |
| Cache/Queue  | Redis                    | 7.x      |
| Auth         | Laravel Sanctum          | 3.x      |
| WebSockets   | Laravel Reverb           | 1.x      |
| Desktop      | Tauri                    | 2.x      |
| PDF          | DomPDF (Laravel)         | —        |


## 3. CONVENTIONS PHP / LARAVEL

### 3.1 Standards de code
- PSR-12 obligatoire sur tout le code PHP
- Typage strict PHP 8.2 : déclarer tous les types (paramètres, retours, propriétés)
- `declare(strict_types=1);` en tête de chaque fichier PHP
- Utiliser les enums PHP 8.1+ pour les statuts et types
- Utiliser les named arguments pour les appels complexes

### 3.2 Nommage
- **Variables et méthodes** : camelCase en anglais
- **Classes et interfaces** : PascalCase en anglais
- **Colonnes DB et routes** : snake_case en français (ex: montant_ttc, succursale_id)
- **Tables DB** : snake_case pluriel français (ex: ventes, fiches_paie)
- **Constantes** : UPPER_SNAKE_CASE

### 3.3 Architecture — séparation des responsabilités

```
Contrôleur  → reçoit la requête, délègue, retourne la réponse (max 20 lignes)
Action      → une opération métier précise (ex: CreerVenteAction)
Service     → logique réutilisable entre plusieurs actions
Repository  → accès aux données (si requêtes complexes)
Resource    → transformation des données pour l'API JSON
Request     → validation des données entrantes
```

**Règle absolue** : zéro logique métier dans les contrôleurs.

### 3.4 Structure des dossiers

```
app/
├── Actions/
│   ├── mouvementstocks/
│   │   ├── CreerVenteAction.php
│   │   └── AnnulerVenteAction.php
│   ├── Stock/
│   ├── Caisse/
│   ├── Succursales/
│   └── Sync/
├── Services/
│   ├── SyncService.php
│   ├── PlanService.php
│   └── PdfService.php
├── Traits/
│   ├── HasUuid.php           ← UUID auto sur création
│   ├── SyncObservable.php    ← alimente sync_queue automatiquement
│   └── succursaleScoped.php      ← filtre par succursale_id selon le rôle
├── Http/
│   ├── Controllers/
│   │   ├── Api/             ← contrôleurs API REST
│   │   └── Auth/            ← authentification
│   ├── Requests/            ← Form Requests (validation)
│   ├── Resources/           ← API Resources (transformation JSON)
│   └── Middleware/
│       ├── CheckPlanLimit.php
│       ├── succursaleScope.php
│       └── entrepriseScope.php
├── Models/
├── Jobs/
│   └── SyncWorker.php       ← synchronisation offline→cloud
├── Observers/               ← si logique Observer dédiée
├── Notifications/
│   └── CustomResetPassword.php
└── Enums/
    ├── RoleEnum.php         ← super_admin, manager, admin, user
    ├── PlanEnum.php         ← free, premium, pro
    └── StatutVenteEnum.php  ← paye, credit, annule
```

### 3.5 Modèles Eloquent

**Chaque modèle doit obligatoirement avoir :**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use App\Traits\succursaleScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NomModele extends Model
{
    use HasUuid, SyncObservable, succursaleScoped;

    protected $fillable = [
        'uuid',
        'entreprise_id',
        'succursale_id',
        // ... colonnes métier ...
        'synced',
        'device_id',
    ];

    protected $casts = [
        'synced'     => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function company(): BelongsTo
    {
        return $this->belongsTo(entreprise::class);
    }

    public function succursale(): BelongsTo
    {
        return $this->belongsTo(Succursale::class);
    }
}
```

**EXCEPTION** : Le modèle `SyncQueue` ne doit PAS avoir le trait
`SyncObservable` — cela créerait une boucle infinie.

### 3.6 Migrations — template obligatoire

```php
Schema::create('nom_table', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->unsignedBigInteger('entreprise_id');
    $table->unsignedBigInteger('succursale_id')->nullable();
    // ... colonnes métier ...
    $table->boolean('synced')->default(false);
    $table->string('device_id', 64)->nullable();
    $table->timestamps(); // created_at + updated_at obligatoires

    $table->foreign('entreprise_id')
          ->references('id')->on('entreprise')
          ->onDelete('cascade');

    $table->index(['entreprise_id', 'succursale_id']);
    $table->index(['uuid']);
    $table->index(['synced', 'device_id']);
});
```

### 3.7 Form Requests — toujours utiliser

```php
// Jamais de $request->validate() dans le contrôleur
// Toujours une classe Request dédiée

class CreerVenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permissions gérées par middleware
    }

    public function rules(): array
    {
        return [
            'client_id'      => ['nullable', 'exists:clients,uuid'],
            'montant_ttc'    => ['required', 'numeric', 'min:0'],
            'mode_paiement'  => ['required', Rule::enum(ModePaiementEnum::class)],
            'lignes'         => ['required', 'array', 'min:1'],
            'lignes.*.produit_uuid' => ['required', 'exists:produits,uuid'],
            'lignes.*.quantite'     => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant_ttc.required' => 'Le montant est obligatoire.',
            'lignes.required'      => 'La vente doit contenir au moins un produit.',
        ];
    }
}
```

### 3.8 API Resources — toujours utiliser

```php
// Jamais de ->toArray() direct dans le contrôleur
// Toujours une Resource dédiée

class VenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'reference'     => $this->reference,
            'montant_ttc'   => $this->montant_ttc,
            'mode_paiement' => $this->mode_paiement,
            'statut'        => $this->statut,
            'client'        => ClientResource::make($this->whenLoaded('client')),
            'succursale'    => SuccursaleResource::make($this->whenLoaded('branch')),
            'lignes'        => VenteLigneResource::collection($this->whenLoaded('lignes')),
            'vendu_at'      => $this->vendu_at?->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
```

### 3.9 Format de réponse API — standard unique

```php
// Succès
return response()->json([
    'success' => true,
    'data'    => VenteResource::make($vente),
    'message' => 'Vente créée avec succès',
], 201);

// Succès avec pagination
return response()->json([
    'success' => true,
    'data'    => VenteResource::collection($ventes),
    'meta'    => [
        'current_page' => $ventes->currentPage(),
        'total'        => $ventes->total(),
        'per_page'     => $ventes->perPage(),
    ],
]);

// Erreur
return response()->json([
    'success' => false,
    'message' => 'Accès refusé — plan insuffisant',
    'code'    => 'PLAN_LIMIT_REACHED',
], 403);
```

### 3.10 Routes API — nommage et groupement

```php
// routes/api.php
Route::prefix('v1')->middleware(['auth:sanctum', 'company', 'branch.scope'])->group(function () {

    // Toujours utiliser uuid dans les URLs — jamais l'id
    Route::apiResource('ventes', VenteController::class)
         ->parameters(['ventes' => 'uuid']);

    // Routes soumises aux limites Freemium
    Route::post('/clients', [ClientController::class, 'store'])
         ->middleware('plan.limit:clients');

    // Routes Premium uniquement
    Route::get('/export/pdf', [ExportController::class, 'pdf'])
         ->middleware('plan.limit:export_pdf');

    // Routes Succursales uniquement
    Route::apiResource('succursale', SuccursaleController::class)
         ->middleware('plan.limit:Succursales');
});

// Endpoint de santé (pas d'auth)
Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));
```

### 3.11 Règles anti-patterns

```
✗ Jamais de dd() ou dump() dans le code commité
✗ Jamais de requête N+1 — toujours with() pour les relations
✗ Jamais de SQL brut sauf performance critique documentée
✗ Jamais de logique dans les vues Blade
✗ Jamais de $request->all() — toujours $request->validated()
✗ Jamais d'id auto-increment dans les payloads API — toujours uuid
✗ Jamais modifier SyncQueue depuis un Observer (boucle infinie)
✗ Jamais root en DB_USERNAME en production
```


## 4. CONVENTIONS VUE.JS / FRONTEND

### 4.1 Template de composant obligatoire

```vue
<template>
  <!-- Un seul élément racine -->
  <div>
    <!-- Contenu -->
  </div>
</template>

<script setup lang="ts">
// 1. Imports Vue
import { ref, computed, onMounted, watch } from 'vue'
// 2. Imports Router/Store
import { useRouter } from 'vue-router'
import { useVenteStore } from '@/stores/vente'
// 3. Imports composables
import { useFormatCurrency } from '@/composables/useFormatCurrency'
// 4. Imports composants
import PgButton from '@/components/ui/PgButton.vue'

// 5. Props avec types explicites
const props = defineProps<{
  venteUuid: string
  readOnly?: boolean
}>()

// 6. Emits explicites
const emit = defineEmits<{
  saved:     [vente: Vente]
  cancelled: []
}>()

// 7. Stores et composables
const store  = useVenteStore()
const router = useRouter()
const { formatCFA } = useFormatCurrency()

// 8. État local
const loading = ref(false)
const error   = ref<string | null>(null)

// 9. Computed
const total = computed(() => store.totalVentes)

// 10. Lifecycle
onMounted(async () => {
  await store.fetchVente(props.venteUuid)
})

// 11. Méthodes
async function handleSave(): Promise<void> {
  loading.value = true
  try {
    const vente = await store.save()
    emit('saved', vente)
  } catch (err) {
    error.value = 'Erreur lors de la sauvegarde'
  } finally {
    loading.value = false
  }
}
</script>
```

### 4.2 Nommage des fichiers Vue.js

```
components/ui/          ← composants génériques réutilisables
  PgButton.vue          ← PascalCase, préfixe Pg pour PrimeGest
  PgInput.vue
  PgTable.vue
  PgModal.vue
  PgBadge.vue
  PgCard.vue
  SyncIndicator.vue

components/             ← composants métier
  VenteForm.vue
  ProduitCard.vue
  SuccursaleSelector.vue

pages/                  ← une page = une route
  DashboardPage.vue
  Produits/
    Index.vue
  Tiers/
    Index.vue         ← fusion clients + fournisseurs

composables/            ← logique réutilisable
  useVentes.ts
  useStock.ts
  usePlan.ts
  useSyncStatus.ts
  useFormatCurrency.ts  ← formatage CDF/USD

stores/                 ← Pinia stores
  vente.ts
  stock.ts
  plan.ts
  sync.ts
  auth.ts
```

### 4.3 Composables — template

```typescript
// composables/useVentes.ts
import { ref, computed } from 'vue'
import axios from 'axios'
import type { Vente } from '@/types/vente'

export function useVentes() {
  const ventes  = ref<Vente[]>([])
  const loading = ref(false)
  const error   = ref<string | null>(null)

  async function fetchVentes(params = {}): Promise<void> {
    loading.value = true
    error.value   = null
    try {
      const { data } = await axios.get('/api/v1/ventes', { params })
      ventes.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message ?? 'Erreur de chargement'
    } finally {
      loading.value = false
    }
  }

  const total = computed(() =>
    ventes.value.reduce((sum, v) => sum + v.montant_ttc, 0)
  )

  return { ventes, loading, error, total, fetchVentes }
}
```

### 4.4 Stores Pinia — template

```typescript
// stores/vente.ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useVenteStore = defineStore('vente', () => {
  // State
  const ventes  = ref<Vente[]>([])
  const current = ref<Vente | null>(null)
  const loading = ref(false)

  // Getters
  const totalVentes = computed(() =>
    ventes.value.reduce((sum, v) => sum + v.montant_ttc, 0)
  )

  // Actions
  async function fetchVentes(): Promise<void> {
    loading.value = true
    try {
      const { data } = await axios.get('/api/v1/ventes')
      ventes.value  = data.data
    } finally {
      loading.value = false
    }
  }

  return { ventes, current, loading, totalVentes, fetchVentes }
})
```

### 4.5 Palette de couleurs PrimeGest — Tailwind

```
Bleu principal : #1A56A0  → bg-[#1A56A0] text-[#1A56A0]
Bleu foncé     : #0B2D5E  → bg-[#0B2D5E]
Beige fond     : #F5F0E8  → bg-[#F5F0E8]
Beige header   : #d8c2a3  → bg-[#d8c2a3]
Vert succès    : #1A7A4A  → bg-[#1A7A4A]
Orange warning : #C96A00  → bg-[#C96A00]
Rouge danger   : #B91C1C  → bg-[#B91C1C]
```

### 4.6 Règles Tailwind

```
✓ Responsive obligatoire : sm: md: lg: sur tous les layouts
✓ Mobile first : concevoir pour mobile puis adapter desktop
✓ Dark mode : prévoir dark: si demandé
✗ Jamais de style inline (style="...")
✗ Jamais de fichier CSS personnalisé sauf animations
✗ Jamais de classes arbitraires non documentées
```


## 5. SYSTÈME OFFLINE-FIRST

Lire le fichier complet : .claude/OFFLINE_FIRST_SPEC.md

### 5.1 Règles absolues — à ne jamais oublier

```
1. Tous les modèles métier → traits HasUuid + SyncObservable
2. SyncQueue → PAS de SyncObservable (boucle infinie)
3. Payload de sync → jamais l'id local, toujours l'uuid
4. SyncWorker → envoie par batch de 50, ordre chronologique
5. Résolution conflit → Last-Write-Wins par updated_at
6. updated_at → obligatoire sur TOUTES les tables
7. branch_id → obligatoire sur TOUTES les tables métier
```

### 5.2 Checklist avant chaque nouveau modèle

```
- [ ] uuid dans $fillable
- [ ] use HasUuid dans le modèle
- [ ] use SyncObservable dans le modèle
- [ ] use succursaleScoped dans le modèle
- [ ] succursale_id dans la migration
- [ ] use EntrepriseScoped dans le modèle
- [ ] Entreprise_id dans la migration
- [ ] synced (boolean, default false) dans la migration
- [ ] device_id (string, nullable) dans la migration
- [ ] updated_at présent (timestamps())
- [ ] Index sur (company_id, branch_id) et (uuid)
```

### 5.3 Tables qui n'ont PAS offline-first

Ces tables sont cloud uniquement — pas de sync_queue :
- `sync_queue` (évidemment)
- `conflict_log`
- `subscriptions`
- `password_reset_tokens`
- `personal_access_tokens`
- `failed_jobs`


## 6. SUCCURSALES (BRANCHES)

### 6.1 Principe fondamental

- Super Admin → voit TOUT, fait TOUT sur toute l'entreprise
- Manager → voit et fait TOUT sur SA succursale uniquement
- l admin → est un employé de l'entreprise qui est affecté dans l entreprise, il a acces aux pages que le super admin ou manager lui donne, il est ecrit et lit
- User → est un employé de l'entreprise qui est affecté dans l entreprise, il a acces aux pages que le super admin ou manager lui donne, il est lit seulement 

### 6.2 Trait succursalScoped et EntrepriseScoped — obligatoire sur tous les modèles métier

```php
// app/Traits/succursaleScoped.php
trait SuccursaleScoped
{
    protected static function bootSuccursaleScoped(): void
    {
        static::addGlobalScope('succursale', function ($query) {
            $user = auth()->user();
            if ($user && in_array($user->role, ['super_admin','manager', 'admin', 'user' ])) {
                $query->where('succursale_id', $user->succursale_id);
            }
        });
    }
}
```

### 6.3 Règles succursales

```
✓ succursale_id sur toutes les tables métier
✓ Middleware succursaleScope appliqué sur toutes les routes API
✓ Facture : toujours afficher l'adresse de la succursale émettrice
✓ Transfert : confirmation mot de passe super_admin ou manager requis
✓ Dashboard centralisé : super_admin uniquement
✓ Dashboard décentralisé : données de la succursale uniquement
✗ Un manager ne peut JAMAIS voir les données d'une autre succursale
✗ Jamais de transfert sans Hash::check() côté serveur
```

### 6.4 Tables succursales

```
succursale        ← liste des succursales (entreprise_id, nom, adresse, manager_id)
transfers         ← transferts inter-succursales (argent et stock)
```


## 7. SYSTÈME FREEMIUM

### 7.1 Plans et limites

| Limite          | Free    | Premium (7$/mois) | pro (10$/mois)      |
|-----------------|---------|-------------------|---------------------|
| Produits        | 50.     | Illimité          | Illimité            |
| Clients         | 15      | Illimité          | Illimité            |
| Fournisseurs    | 15      | Illimité          | Illimité            |
| Utilisateurs    | 1       | 10                | 20                  |
| Succursales     | ✗       | ✗                 | Illimité            |
| Dettes/créances | ✗       | ✓                 | ✓                   |
| Export PDF/Excel| ✗       | ✓                 | ✓                   |
| Stockage cloud  | 200 MB  | 5 GB              | 10 GB               |

### 7.2 Middleware CheckPlanLimit — toujours appliquer

```php
// Sur chaque route soumise aux limites
Route::post('/clients', [ClientController::class, 'store'])
     ->middleware('plan.limit:clients');

Route::post('/branches', [BranchController::class, 'store'])
     ->middleware('plan.limit:branches');
```

### 7.3 Vérification côté Vue.js

```typescript
// Toujours vérifier avant d'afficher un bouton d'action
const planStore = usePlanStore()

// Dans le template
<PgButton
  @click="addClient"
  :disabled="!planStore.canUse('clients')"
>
  Ajouter un client
  <span v-if="!planStore.canUse('clients')" class="badge-premium">
    Premium
  </span>
</PgButton>
```


## 8. SÉCURITÉ

### 8.1 Règles fondamentales

```
✓ Toujours HTTPS en production (SSL strict Cloudflare)
✓ Tokens Sanctum avec expiration
✓ Rate limiting sur toutes les routes publiques
✓ Hash::check() pour validation mot de passe (transferts)
✓ Validation stricte de toutes les entrées (Form Requests)
✓ CORS configuré pour primegest.app uniquement en prod
✓ Logs d'audit sur les actions sensibles (transferts, suppression)
✗ Jamais de mot de passe en clair dans les logs
✗ Jamais de données sensibles dans les URLs
✗ Jamais de token dans le localStorage (utiliser cookies httpOnly)
```

### 8.2 Middleware stack sur les routes protégées

```php
Route::middleware([
    'auth:sanctum',    // authentifié
    'company',         // company_id résolu et vérifié
    'branch.scope',    // filtre par branch_id selon le rôle
])->group(function () {
    // routes ici
});
```


## 9. TESTS

### 9.1 Backend — PHPUnit

```php
// Toute nouvelle feature doit avoir son test
// Nommage : tests/Feature/NomFeatureTest.php

class CreerVenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_peut_creer_vente(): void
    {
        $company = Company::factory()->create();
        $user    = User::factory()->superAdmin()->for($company)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/ventes', [
                'montant_ttc'   => 50.00,
                'mode_paiement' => 'cash',
                'lignes'        => [
                    ['produit_uuid' => $produit->uuid, 'quantite' => 1],
                ],
            ]);

        $response->assertCreated()
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['uuid', 'reference']]);
    }
}
```

### 9.2 Commandes utiles

```bash
php artisan test                          # tous les tests
php artisan test --filter CreerVenteTest  # test spécifique
php artisan test --coverage               # avec couverture
npm run test                              # tests Vitest frontend
```


## 10. COMMANDES ARTISAN UTILES

```bash
# Développement
php artisan serve                  # démarrer le serveur local
npm run dev                        # démarrer Vite (frontend)
php artisan queue:work             # démarrer les workers de queue

# Base de données
php artisan migrate                # lancer les migrations
php artisan migrate:fresh --seed   # reset complet avec données de test
php artisan db:seed                # seeder uniquement

# Cache
php artisan config:clear           # vider config
php artisan cache:clear            # vider cache
php artisan view:clear             # vider vues compilées
php artisan route:clear            # vider routes

# PrimeGest spécifique
php artisan primegest:device-id    # générer ID unique du poste
php artisan primegest:sync         # forcer une synchronisation manuelle
php artisan primegest:check-plans  # vérifier les abonnements expirés

# Production
php artisan config:cache           # cacher la config (prod uniquement)
php artisan route:cache            # cacher les routes (prod uniquement)
php artisan view:cache             # cacher les vues (prod uniquement)
php artisan storage:link           # lier le stockage public
```


## 11. SKILLS SPÉCIALISÉS

Avant de travailler sur ces sujets, lire le skill correspondant :

| Sujet                          | Fichier à lire                          |
|--------------------------------|-----------------------------------------|
| Base de données et migrations  | .claude/skills/SKILL_DATABASE.md        |
| API REST et contrôleurs        | .claude/skills/SKILL_API.md             |
| Composants Vue.js et UI        | .claude/skills/SKILL_FRONTEND.md        |
| Système offline et sync        | .claude/skills/SKILL_OFFLINE.md         |
| Permissions et Freemium        | .claude/skills/SKILL_SECURITY.md        |
| Spec offline complète          | docs/OFFLINE_FIRST_SPEC.md              |


## 12. RÈGLES ABSOLUES — RÉSUMÉ FINAL

```
Ne jamais :
  ✗ Commiter le fichier .env
  ✗ Utiliser l'id local dans les payloads API ou sync
  ✗ Ajouter SyncObservable sur le modèle SyncQueue
  ✗ Écrire de la logique dans les contrôleurs
  ✗ Faire des requêtes N+1 (toujours with())
  ✗ Laisser un manager voir les données d'une autre succursale
  ✗ Effectuer un transfert sans vérification de mot de passe côté serveur
  ✗ Utiliser $request->all() — toujours $request->validated()
  ✗ Pousser directement sur la branche main

Toujours :
  ✓ uuid sur chaque modèle (trait HasUuid)
  ✓ succursale_id sur chaque table métier
  ✓ entreprise_id sur chaque table métier
  ✓ updated_at sur chaque table (résolution conflits)
  ✓ Form Request pour toute validation
  ✓ API Resource pour toute réponse JSON
  ✓ Middleware plan.limit sur les routes Freemium
  ✓ Middleware branch.scope sur toutes les routes API
  ✓ Tests avant de merger une feature
  ✓ Commentaires en français
  ✓ Interface en français et en anglais uniquement  
```
