# Plan d'implémentation — Système de gates d'abonnement
**Spec :** `docs/superpowers/specs/2026-05-26-subscription-gates-design.md`
**Date :** 2026-05-26

---

## Étape 1 — Backend : corriger les routes `web.php`

**Fichier :** `routes/web.php`

Remplacer les routes succursales et transferts existantes dans le groupe `auth` par :

```php
// Succursales — lecture libre, écriture plan pro
Route::middleware('auth')->group(function () {
    Route::get('/succursales', [SuccursaleController::class, 'index'])->name('succursales.index');
    Route::get('/succursales/{succursale}', [SuccursaleController::class, 'show'])->name('succursales.show');
    Route::get('/succursales-exit', [SuccursaleController::class, 'exit'])->name('succursales.exit');
});

Route::middleware(['auth', 'plan:succursales'])->group(function () {
    Route::post('/succursales', [SuccursaleController::class, 'store'])->name('succursales.store');
    Route::put('/succursales/{succursale}', [SuccursaleController::class, 'update'])->name('succursales.update');
    Route::delete('/succursales/{succursale}', [SuccursaleController::class, 'destroy'])->name('succursales.destroy');
});

// Transferts — plan pro (fonctionnalité succursales)
Route::middleware('auth')->group(function () {
    Route::get('/transferts', [TransfertController::class, 'index'])->name('transferts.index');
});

Route::middleware(['auth', 'plan:succursales'])->group(function () {
    Route::post('/transferts/caisse', [TransfertController::class, 'storeCaisse'])->name('transferts.caisse');
    Route::post('/transferts/stock', [TransfertController::class, 'storeStock'])->name('transferts.stock');
    Route::post('/transferts/{transfert}/approve', [TransfertController::class, 'approve'])->name('transferts.approve');
    Route::post('/transferts/{transfert}/reject', [TransfertController::class, 'reject'])->name('transferts.reject');
});
```

Supprimer les lignes redondantes de l'ancien groupe `auth` (lignes 47-58).

**Test :** Avec un compte premium, POST /succursales → 403. GET /succursales → 200.

---

## Étape 2 — Backend : invalider le cache après changement de plan

### 2a. `ConfirmerPaiementWebhookAction.php`

Après le `DB::transaction(...)`, ajouter :

```php
use Illuminate\Support\Facades\Cache;

// dans execute(), après la transaction :
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

### 2b. `ActivateSubscriptionAction.php`

Même ajout après le `DB::transaction(...)` :

```php
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

### 2c. `CheckSubscriptionsExpiration.php`

Dans `downgradeExpired()`, après chaque `$entreprise->update([...])` :

```php
Cache::forget("inertia.entreprise.{$entreprise->id}");
Cache::forget("inertia.has_succursales.{$entreprise->id}");
```

**Test :** Activer un plan pro via admin → recharger la page → plan_limits mis à jour sans attendre 60s.

---

## Étape 3 — Frontend : créer `FeatureGate.vue`

**Fichier :** `resources/js/components/FeatureGate.vue`

```vue
<template>
  <slot v-if="allowed" />

  <template v-else-if="mode === 'block'">
    <div class="feature-gate-block">
      <span class="feature-gate-block__icon">🔒</span>
      <p class="feature-gate-block__title">Fonctionnalité {{ planLabel }} requise</p>
      <p class="feature-gate-block__desc">{{ description }}</p>
      <a :href="`/abonnement?plan=${requiredPlan}`" class="feature-gate-block__btn">
        Passer au plan {{ planLabel }} →
      </a>
    </div>
  </template>

  <template v-else-if="mode === 'inline'">
    <a :href="`/abonnement?plan=${requiredPlan}`" class="feature-gate-inline" :title="description">
      🔒 {{ planLabel }}
    </a>
  </template>
  <!-- mode === 'hide' : rien -->
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePlanStore } from '@/stores/usePlanStore'

const props = withDefaults(defineProps<{
  feature: string
  mode?: 'block' | 'inline' | 'hide'
}>(), { mode: 'block' })

const REQUIRED_PLAN: Record<string, 'premium' | 'pro'> = {
  succursales:    'pro',
  exports:        'premium',
  dette_tracking: 'premium',
  reductions:     'premium',
}

const DESCRIPTIONS: Record<string, string> = {
  succursales:    'Les succursales sont disponibles uniquement avec le plan Pro (10$/mois).',
  exports:        'L\'export PDF/Excel est disponible à partir du plan Premium (7$/mois).',
  dette_tracking: 'Le suivi des dettes et créances est disponible à partir du plan Premium (7$/mois).',
  reductions:     'Les réductions sont disponibles à partir du plan Premium (7$/mois).',
}

const planStore     = usePlanStore()
const allowed       = computed(() => planStore.can(props.feature))
const requiredPlan  = computed(() => REQUIRED_PLAN[props.feature] ?? 'premium')
const planLabel     = computed(() => requiredPlan.value === 'pro' ? 'Pro' : 'Premium')
const description   = computed(() => DESCRIPTIONS[props.feature] ?? 'Cette fonctionnalité nécessite un plan supérieur.')
</script>

<style scoped>
.feature-gate-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1.5rem;
  border: 1px dashed #d1c4a8;
  border-radius: 1rem;
  background: #fdfaf4;
  text-align: center;
}
.feature-gate-block__icon  { font-size: 1.5rem; }
.feature-gate-block__title { font-weight: 700; color: #1d160f; font-size: 0.95rem; }
.feature-gate-block__desc  { font-size: 0.82rem; color: #7a6040; max-width: 28rem; }
.feature-gate-block__btn {
  margin-top: 0.5rem;
  display: inline-block;
  padding: 0.55rem 1.25rem;
  border-radius: 999px;
  background: linear-gradient(135deg, #d69a1a, #ffd070);
  color: #19130c;
  font-weight: 700;
  font-size: 0.85rem;
  text-decoration: none;
}
.feature-gate-block__btn:hover { opacity: 0.88; }

.feature-gate-inline {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #b9780f;
  text-decoration: none;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  border: 1px solid #e8c97a;
  background: #fdf6e3;
  cursor: pointer;
}
.feature-gate-inline:hover { background: #faeec7; }
</style>
```

**Test :** Monter le composant avec `feature="succursales"` sur un compte free/premium → carte visible. Sur compte pro → slot s'affiche normalement.

---

## Étape 4 — Frontend : mettre à jour `Succursales/Index.vue`

**Importer le composant :**
```ts
import FeatureGate from '@/components/FeatureGate.vue'
```

**Bouton "Nouvelle succursale" :**
```vue
<!-- AVANT -->
<button @click="openModal()">Nouvelle succursale</button>

<!-- APRÈS -->
<FeatureGate feature="succursales">
  <button @click="openModal()">Nouvelle succursale</button>
</FeatureGate>
```

**Boutons Modifier et Supprimer sur chaque ligne :**
```vue
<!-- AVANT -->
<button @click="openModal(s)">Modifier</button>
<button @click="openDelete(s.id)">Supprimer</button>

<!-- APRÈS -->
<FeatureGate feature="succursales" mode="inline">
  <button @click="openModal(s)">Modifier</button>
</FeatureGate>
<FeatureGate feature="succursales" mode="inline">
  <button @click="openDelete(s.id)">Supprimer</button>
</FeatureGate>
```

---

## Étape 5 — Frontend : mettre à jour `Transferts/Index.vue`

**Importer le composant :**
```ts
import FeatureGate from '@/components/FeatureGate.vue'
```

**Boutons création de transfert :**
```vue
<!-- AVANT -->
<button @click="openCaisse()">Nouveau transfert caisse</button>
<button @click="openStock()">Nouveau transfert stock</button>

<!-- APRÈS -->
<FeatureGate feature="succursales">
  <button @click="openCaisse()">Nouveau transfert caisse</button>
</FeatureGate>
<FeatureGate feature="succursales">
  <button @click="openStock()">Nouveau transfert stock</button>
</FeatureGate>
```

**Boutons approve/reject sur chaque ligne :**
```vue
<FeatureGate feature="succursales" mode="inline">
  <button @click="approve(t.id)">Approuver</button>
</FeatureGate>
<FeatureGate feature="succursales" mode="inline">
  <button @click="reject(t.id)">Rejeter</button>
</FeatureGate>
```

---

## Étape 6 — Frontend : mettre à jour `Tiers/Index.vue`

**Importer le composant :**
```ts
import FeatureGate from '@/components/FeatureGate.vue'
```

Envelopper les liens vers `/creances-dettes/clients/{id}` et `/creances-dettes/fournisseurs/{id}` :

```vue
<!-- Bouton/lien détail dettes clients -->
<FeatureGate feature="dette_tracking" mode="inline">
  <button @click="router.get(`/creances-dettes/clients/${c.id}`)">Voir dettes</button>
</FeatureGate>

<!-- Bouton/lien détail dettes fournisseurs -->
<FeatureGate feature="dette_tracking" mode="inline">
  <button @click="router.get(`/creances-dettes/fournisseurs/${f.id}`)">Voir dettes</button>
</FeatureGate>
```

Et les colonnes `créance` / `dette` dans le tableau :
```vue
<FeatureGate feature="dette_tracking" mode="inline">
  <td>{{ c.creance }}</td>
</FeatureGate>
```

---

## Étape 7 — Frontend : mettre à jour `MouvementStock/Index.vue`

**Importer le composant :**
```ts
import FeatureGate from '@/components/FeatureGate.vue'
```

Localiser le bouton d'export PDF et envelopper :
```vue
<FeatureGate feature="exports" mode="inline">
  <button @click="exportPdf()">Exporter PDF</button>
</FeatureGate>
```

---

## Étape 8 — Vérification finale

```bash
# Lancer les tests backend
php artisan test --filter CheckPlanLimitTest

# Vérifier que les routes sont bien enregistrées
php artisan route:list | grep -E "succursale|transfert"

# Build frontend
npm run build

# Vider les caches config/route
php artisan config:clear && php artisan route:clear
```

Tester manuellement avec 3 comptes :
- **Free** → succursales GET visible, boutons remplacés par FeatureGate block
- **Premium** → idem (succursales bloquées), dettes/exports OK
- **Pro** → tout accessible, aucun gate visible

---

## Ordre d'exécution recommandé

```
Étape 1 → Étape 2 → Étape 3 → Étapes 4-7 (parallèles) → Étape 8
```

Les étapes 4 à 7 sont indépendantes entre elles et peuvent être faites dans n'importe quel ordre.
