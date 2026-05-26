# Spec : Système de gates d'abonnement — PrimeGest
**Date :** 2026-05-26
**Statut :** Approuvé

---

## 1. Objectif

Automatiser l'accès aux fonctionnalités selon le plan souscrit par chaque entreprise.
Règle fondamentale : **le plan Premium ne donne pas accès aux succursales** (réservé au plan Pro).

Le système doit être cohérent sur trois couches :
1. Backend : middleware Laravel bloque les routes non autorisées
2. Frontend : composant `<FeatureGate>` masque ou remplace les éléments UI
3. Cache : invalidé immédiatement après chaque changement de plan

---

## 2. Source de vérité : `config/plans.php`

Le fichier existant est la seule source de vérité. Aucune duplication.

| Feature | free | premium | pro |
|---------|------|---------|-----|
| `succursales` | false | **false** | true |
| `dette_tracking` | false | true | true |
| `reductions` | false | true | true |
| `exports` | false | true | true |
| `produits` | 50 | -1 | -1 |
| `clients` | 20 | -1 | -1 |
| `fournisseurs` | 20 | -1 | -1 |
| `users` | 3 | -1 | -1 |

Le middleware `CheckPlanLimit` lit ce fichier — pas de hardcoding ailleurs.

---

## 3. Backend guards

### 3.1 Routes à corriger dans `routes/web.php`

**Succursales** — toutes les routes d'écriture doivent avoir `plan:succursales` :

```php
// AVANT
Route::post('/succursales', ...)  ->middleware('plan:succursales');
Route::put('/succursales/{s}', ...)  // aucun guard
Route::delete('/succursales/{s}', ...)  // aucun guard

// APRÈS — regrouper en un seul groupe
Route::middleware(['auth', 'plan:succursales'])->group(function () {
    Route::post('/succursales', [SuccursaleController::class, 'store'])->name('succursales.store');
    Route::put('/succursales/{succursale}', [SuccursaleController::class, 'update'])->name('succursales.update');
    Route::delete('/succursales/{succursale}', [SuccursaleController::class, 'destroy'])->name('succursales.destroy');
});

// GET restent sans guard (on peut voir la page, mais les actions sont bloquées)
Route::middleware('auth')->group(function () {
    Route::get('/succursales', [SuccursaleController::class, 'index'])->name('succursales.index');
    Route::get('/succursales/{succursale}', [SuccursaleController::class, 'show'])->name('succursales.show');
    Route::get('/succursales-exit', [SuccursaleController::class, 'exit'])->name('succursales.exit');
});
```

**Transferts** — fonctionnalité liée aux succursales, même gate :

```php
Route::middleware(['auth', 'plan:succursales'])->group(function () {
    Route::post('/transferts/caisse', [TransfertController::class, 'storeCaisse'])->name('transferts.caisse');
    Route::post('/transferts/stock', [TransfertController::class, 'storeStock'])->name('transferts.stock');
    Route::post('/transferts/{transfert}/approve', [TransfertController::class, 'approve'])->name('transferts.approve');
    Route::post('/transferts/{transfert}/reject', [TransfertController::class, 'reject'])->name('transferts.reject');
});
```

### 3.2 Routes déjà correctes (ne pas modifier)

- `POST /produits` → `plan:produits` ✓
- `POST /clients` → `plan:clients` ✓
- `POST /fournisseurs` → `plan:fournisseurs` ✓
- `plan:dette_tracking` → groupe complet ✓
- `plan:users` → ✓

### 3.3 `CheckPlanLimit` middleware

Aucune modification. Il lit `config/plans.{plan}.{feature}` et retourne 403 JSON
ou une page Inertia `Upgrade` selon le type de requête.

---

## 4. Invalidation du cache après changement de plan

Le cache Inertia `inertia.entreprise.{$eid}` a un TTL de 60s. Après une activation
ou un downgrade, il doit être invalidé immédiatement pour que le frontend reflète
le nouveau plan sans attendre.

### 4.1 `ConfirmerPaiementWebhookAction` — ajouter après la transaction

```php
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

### 4.2 `ActivateSubscriptionAction` — même ajout après la transaction

```php
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

### 4.3 `CheckSubscriptionsExpiration` — ajouter dans `downgradeExpired()`

Après chaque `$entreprise->update(['plan' => 'free', ...])` :

```php
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

---

## 5. Composant frontend `<FeatureGate>`

### 5.1 Fichier

`resources/js/components/FeatureGate.vue`

### 5.2 Props

| Prop | Type | Défaut | Description |
|------|------|--------|-------------|
| `feature` | `string` | requis | Clé de la feature dans `plan_limits` |
| `mode` | `'block' \| 'inline' \| 'hide'` | `'block'` | Comportement si bloqué |

### 5.3 Mapping feature → plan requis (interne)

```ts
const REQUIRED_PLAN: Record<string, 'premium' | 'pro'> = {
  succursales:    'pro',
  exports:        'premium',
  dette_tracking: 'premium',
  reductions:     'premium',
}
```

### 5.4 Comportement par mode

**`block`** (défaut) — remplace le contenu par une carte upgrade :
```
┌─────────────────────────────────────────────┐
│  🔒  Fonctionnalité Pro                     │
│  Les succursales sont disponibles           │
│  uniquement avec le plan Pro (10$/mois).    │
│                                             │
│  [  Passer au plan Pro →  ]                 │
└─────────────────────────────────────────────┘
```

**`inline`** — remplace par un badge `🔒 Pro` ou `🔒 Premium` cliquable
qui redirige vers `/abonnement?plan=pro`.

**`hide`** — n'affiche rien si le plan est insuffisant.

### 5.5 Logique interne

```ts
const planStore = usePlanStore()
const allowed = computed(() => planStore.can(props.feature))
const requiredPlan = computed(() => REQUIRED_PLAN[props.feature] ?? 'premium')
```

Si `allowed.value === true` → slot default.
Sinon → afficher le mode bloqué correspondant.

---

## 6. Pages à mettre à jour

### 6.1 `Succursales/Index.vue`

- Bouton "Nouvelle succursale" → `<FeatureGate feature="succursales">`
- Boutons "Modifier" et "Supprimer" sur chaque ligne → `<FeatureGate feature="succursales" mode="inline">`

### 6.2 `Transferts/Index.vue`

- Boutons "Nouveau transfert caisse" et "Nouveau transfert stock" → `<FeatureGate feature="succursales">`
- Boutons "Approuver" / "Rejeter" → `<FeatureGate feature="succursales" mode="inline">`

### 6.3 `Tiers/Index.vue`

- Section dettes/créances → `<FeatureGate feature="dette_tracking">`

### 6.4 `MouvementStock/Index.vue`

- Bouton export PDF/Excel → `<FeatureGate feature="exports" mode="inline">`

---

## 7. Comportement en cas d'expiration

Le downgrade est géré en deux filets de sécurité :

1. **Cron `subscriptions:check`** — tourne chaque nuit à 02h00, downgrade les
   entreprises expirées et vide le cache.
2. **Middleware `EnsurePlanNotExpired`** — sur chaque requête authentifiée,
   vérifie la date d'expiration et downgrade en temps réel si le cron n'a pas tourné.

Quand une entreprise pro revient en free après expiration :
- Les succursales existantes restent en DB (données préservées).
- Les routes d'écriture (POST/PUT/DELETE succursales, transferts) sont bloquées
  par le middleware `plan:succursales`.
- Le frontend affiche la carte upgrade à la place des boutons d'action.
- À la prochaine souscription pro, l'accès est immédiatement restauré.

---

## 8. Récapitulatif des fichiers modifiés

| Fichier | Type de changement |
|---------|-------------------|
| `routes/web.php` | Regrouper succursales + ajouter guard sur transferts |
| `app/Actions/Subscription/ConfirmerPaiementWebhookAction.php` | `Cache::forget()` après transaction |
| `app/Actions/Subscription/ActivateSubscriptionAction.php` | `Cache::forget()` après transaction |
| `app/Console/Commands/CheckSubscriptionsExpiration.php` | `Cache::forget()` après downgrade |
| `resources/js/components/FeatureGate.vue` | Nouveau composant |
| `resources/js/Pages/Succursales/Index.vue` | Envelopper actions CRUD |
| `resources/js/Pages/Transferts/Index.vue` | Envelopper actions transfert |
| `resources/js/Pages/Tiers/Index.vue` | Envelopper section dettes |
| `resources/js/Pages/MouvementStock/Index.vue` | Envelopper export |

---

## 9. Hors périmètre

- Gestion des paiements Netikash (déjà fonctionnelle)
- Interface admin (`/admin/plans`) — déjà opérationnelle
- Système offline/sync — non impacté
- Ajout de nouveaux plans ou nouveaux prix
