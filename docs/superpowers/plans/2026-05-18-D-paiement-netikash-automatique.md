# Paiement Netikash Automatique — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le bouton "Passer au Premium/Pro" de `Upgrade.vue` (qui ouvre un mailto) par une redirection vers `/abonnement` avec plan pré-sélectionné. Supprimer le flux manuel `storeDemande`. Simplifier les couleurs de `Upgrade.vue`.

**Architecture:** `Upgrade.vue` redirige vers `/abonnement?plan=X` via `router.visit()`. `Abonnement/Index.vue` lit le query param au montage. `AbonnementController.storeDemande()` et sa route sont supprimés.

**Tech Stack:** Vue 3 Composition API, Inertia.js `router`, Laravel routes

---

## Fichiers modifiés

- Modify: `resources/js/Pages/Upgrade.vue`
- Modify: `resources/js/Pages/Abonnement/Index.vue`
- Modify: `app/Http/Controllers/AbonnementController.php`
- Modify: `routes/web.php` (ligne 39)

---

### Task 1 : Supprimer la route et méthode storeDemande

**Files:**
- Modify: `routes/web.php:39`
- Modify: `app/Http/Controllers/AbonnementController.php`

- [ ] **Step 1 : Écrire le test vérifiant que la route n'existe plus**

Dans `tests/Feature/AbonnementTest.php` (créer si absent) :

```php
<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_demande_route_does_not_exist(): void
    {
        $entreprise = Entreprise::factory()->create(['plan' => 'free']);
        $user = User::factory()->create([
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post('/abonnement/demande', [
            'plan' => 'premium',
            'duree' => 1,
            'payment_method' => 'mtn',
            'payment_reference' => 'REF123',
        ]);

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2 : Lancer le test — vérifier qu'il échoue**

```bash
cd "/var/www/primegest" && php artisan test --filter AbonnementTest::test_store_demande_route_does_not_exist
```

Expected: FAIL — la route retourne 302 ou 200 (elle existe encore)

- [ ] **Step 3 : Supprimer la route dans `routes/web.php`**

Trouver et supprimer la ligne 39 :
```php
Route::post('/abonnement/demande', [\App\Http\Controllers\AbonnementController::class, 'storeDemande'])->name('abonnement.demande');
```

- [ ] **Step 4 : Supprimer la méthode `storeDemande` dans `AbonnementController.php`**

Supprimer la méthode `storeDemande()` entièrement (le bloc `public function storeDemande(Request $request): RedirectResponse { ... }`).
Supprimer aussi l'import `use Illuminate\Http\RedirectResponse;` s'il n'est plus utilisé ailleurs dans le fichier.

- [ ] **Step 5 : Lancer le test — vérifier qu'il passe**

```bash
php artisan test --filter AbonnementTest::test_store_demande_route_does_not_exist
```

Expected: PASS

- [ ] **Step 6 : Lancer la suite complète pour détecter les régressions**

```bash
php artisan test
```

Expected: tous les tests passent (81+)

- [ ] **Step 7 : Commit**

```bash
git add routes/web.php app/Http/Controllers/AbonnementController.php tests/Feature/AbonnementTest.php
git commit -m "feat(abonnement): supprimer route et méthode storeDemande (flux manuel)"
```

---

### Task 2 : Lire le query param `plan` dans Abonnement/Index.vue

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : Ajouter `onMounted` pour lire le query param**

Dans le script setup de `Abonnement/Index.vue`, l'import `onMounted` est déjà présent.
Ajouter après la déclaration de `form` :

```typescript
onMounted(() => {
    const params = new URLSearchParams(window.location.search)
    const planParam = params.get('plan')
    if (planParam === 'premium' || planParam === 'pro') {
        form.value.plan = planParam
    }
})
```

- [ ] **Step 2 : Vérification manuelle**

```bash
npm run dev
```

Ouvrir `http://localhost/abonnement?plan=pro`. Vérifier que la card "Pro" est sélectionnée (bordure visible) au chargement.
Ouvrir `http://localhost/abonnement?plan=premium`. Vérifier que "Premium" est sélectionné.
Ouvrir `http://localhost/abonnement` sans param. Vérifier que "Premium" est sélectionné par défaut (valeur initiale du form).

- [ ] **Step 3 : Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "feat(abonnement): pré-sélectionner le plan via query param ?plan="
```

---

### Task 3 : Refactorer Upgrade.vue — redirection + simplification couleurs

**Files:**
- Modify: `resources/js/Pages/Upgrade.vue`

- [ ] **Step 1 : Remplacer la fonction `contacter()` par une redirection**

Dans `<script setup>`, remplacer entièrement la fonction `contacter()` :

```javascript
// Avant
function contacter(targetPlan) {
    const dureeLabel = ...
    window.location.href = `mailto:support@primegest.app?...`
}

// Après
function allerAbonnement(targetPlan) {
    router.visit('/abonnement', { data: { plan: targetPlan } })
}
```

- [ ] **Step 2 : Mettre à jour les boutons dans le template**

Remplacer chaque `@click="contacter('premium')"` et `@click="contacter('pro')"` par `@click="allerAbonnement('premium')"` et `@click="allerAbonnement('pro')"`.

- [ ] **Step 3 : Supprimer la section "Comment upgrader ?"**

Supprimer entièrement le bloc `<section>` contenant :
```html
<section class="max-w-xl mx-auto px-4 pb-16">
    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-8 text-center">
        ...
        <a href="mailto:support@primegest.app" ...>
        ...
    </div>
</section>
```

- [ ] **Step 4 : Simplifier les couleurs de Upgrade.vue**

**Cards plan :**
- `.plan-card--featured` → supprimer le style `border: 2px solid #2563eb; box-shadow: 0 4px 24px rgba(37,99,235,0.12);`. Le remplacer par `border: 2px solid #111;`
- `.plan-card--featured:hover` → `box-shadow: 0 8px 32px rgba(0,0,0,0.07);` (même que .plan-card:hover)

**Badge "RECOMMANDÉ" :**
Supprimer le div ruban entièrement :
```html
<div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-blue-600 text-white ...">
    RECOMMANDÉ
</div>
```

**Badge plan :**
- Badge Premium : `class="plan-badge bg-gray-100 text-gray-700"` (supprimer `bg-black text-blue-400`)
- Badge Pro : `class="plan-badge bg-gray-100 text-gray-700"` (supprimer `bg-gray-900 text-gold`)

**Prix Premium :**
- Supprimer `class="plan-price text-blue-600"` → `class="plan-price"` (le CSS de base `.plan-price` donne déjà `color: #111`)

**Badge feature bloquée :**
```html
<!-- Avant -->
class="inline-flex items-center gap-2 mb-6 px-4 py-2 rounded-full border border-blue-200 bg-blue-50 text-sm text-blue-600 font-mono tracking-wide"

<!-- Après -->
class="inline-flex items-center gap-2 mb-6 px-4 py-2 rounded-full border border-gray-200 bg-gray-50 text-sm text-gray-600 font-mono tracking-wide"
```
Remplacer aussi `bg-blue-500 animate-pulse` par `bg-gray-400 animate-pulse`.

**Bouton Premium :**
```html
<!-- Avant -->
class="w-full py-2.5 rounded-lg bg-blue-600 text-white text-sm font-black font-mono tracking-wide hover:bg-blue-700 ..."

<!-- Après -->
class="w-full py-2.5 rounded-lg bg-[#1A56A0] text-white text-sm font-black font-mono tracking-wide hover:bg-[#0B2D5E] ..."
```

**Supprimer l'import Google Fonts** du style scoped :
```css
/* Supprimer cette ligne */
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Mono:wght@400;500&display=swap');
```

- [ ] **Step 5 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir `http://localhost/upgrade` (ou déclencher depuis une page bloquée par plan).
Vérifier :
- Clic "Passer au Premium →" → redirige vers `/abonnement?plan=premium` ✓
- Clic "Passer au Pro →" → redirige vers `/abonnement?plan=pro` ✓
- Aucun mailto ne s'ouvre ✓
- Pas de section "Comment upgrader ?" ✓
- Aucune couleur bleue/violette/dorée sur les cards ✓
- Seul le bouton Premium est bleu (#1A56A0) ✓

- [ ] **Step 6 : Commit**

```bash
git add resources/js/Pages/Upgrade.vue
git commit -m "feat(upgrade): remplacer mailto par redirection /abonnement + simplifier couleurs"
```
