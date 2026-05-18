# Abonnement Page — Design Minimaliste — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Supprimer toutes les couleurs superflues de `Abonnement/Index.vue` — blanc/gris partout, seul le bouton "Payer" reste en bleu `#1A56A0`.

**Architecture:** Modification unique de `resources/js/Pages/Abonnement/Index.vue`. Aucun changement backend, aucun test PHPUnit nécessaire. Vérification visuelle dans le navigateur.

**Tech Stack:** Vue 3 Composition API, Tailwind CSS, Inertia.js

---

## Fichiers modifiés

- Modify: `resources/js/Pages/Abonnement/Index.vue`

---

### Task 1 : Cards de plan et badge plan actuel

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : Simplifier `planColor` computed**

Remplacer le computed `planColor` (lignes ~83-88) :

```typescript
// Avant
const planColor = computed(() => ({
    free:    'bg-gray-100 text-gray-700',
    premium: 'bg-blue-100 text-blue-800',
    pro:     'bg-purple-100 text-purple-800',
})[props.plan] ?? 'bg-gray-100 text-gray-700')

// Après — une seule valeur constante
const planColor = 'bg-gray-100 text-gray-700'
```

- [ ] **Step 2 : Supprimer `planColor` comme computed, le remplacer par une constante**

Changer la déclaration `const planColor = computed(...)` en `const planColor = 'bg-gray-100 text-gray-700'`.
Le template utilise `:class="planColor"` — ça fonctionne avec une string.

- [ ] **Step 3 : Neutraliser les cards Premium / Pro**

Dans le template, trouver le bloc `<!-- Choix du plan -->` avec la `grid grid-cols-2`. Remplacer les classes conditionnelles :

```html
<!-- Avant -->
:class="['border-2 rounded-xl p-4 text-left transition',
         form.plan === 'premium' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-200']"

<!-- Après -->
:class="['border-2 rounded-xl p-4 text-left transition',
         form.plan === 'premium' ? 'border-gray-900' : 'border border-gray-200']"
```

```html
<!-- Avant -->
:class="['border-2 rounded-xl p-4 text-left transition',
         form.plan === 'pro' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-200']"

<!-- Après -->
:class="['border-2 rounded-xl p-4 text-left transition',
         form.plan === 'pro' ? 'border-gray-900' : 'border border-gray-200']"
```

- [ ] **Step 4 : Neutraliser les prix des cards**

Dans la card Premium, remplacer `text-blue-600` par `text-gray-900` sur le prix.
Dans la card Pro, si un `text-purple-600` existe sur le prix, le remplacer par `text-gray-900`.

- [ ] **Step 5 : Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "style(abonnement): neutraliser cards plan premium/pro"
```

---

### Task 2 : Alerte expiration, devise, récap montant

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : Alerte expiration sans fond jaune**

Trouver le bloc `v-if="joursWarning && plan !== 'free'"`. Remplacer :

```html
<!-- Avant -->
<div class="mt-3 flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-sm text-yellow-800">
    <span>⚠</span>
    <span>Expire dans <strong>{{ jours_restants }} jour{{ jours_restants !== 1 ? 's' : '' }}</strong> — pensez à renouveler.</span>
</div>

<!-- Après -->
<p class="mt-2 text-sm text-gray-600">
    Expire dans <strong>{{ jours_restants }} jour{{ jours_restants !== 1 ? 's' : '' }}</strong> — pensez à renouveler.
</p>
```

- [ ] **Step 2 : Boutons devise sans fond bleu**

Trouver les boutons USD/CDF. Remplacer :

```html
<!-- Avant -->
:class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
         form.devise === 'USD' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']"

<!-- Après -->
:class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
         form.devise === 'USD' ? 'border-gray-900 text-gray-900' : 'border-gray-200 text-gray-600']"
```

```html
<!-- Avant -->
:class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
         form.devise === 'CDF' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']"

<!-- Après -->
:class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
         form.devise === 'CDF' ? 'border-gray-900 text-gray-900' : 'border-gray-200 text-gray-600']"
```

- [ ] **Step 3 : Récap montant sans fond gris**

Trouver le div `bg-gray-50 rounded-lg px-4 py-3`. Remplacer :

```html
<!-- Avant -->
<div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">

<!-- Après -->
<div class="border-t border-gray-200 pt-3 text-sm text-gray-700">
```

- [ ] **Step 4 : Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "style(abonnement): supprimer couleurs alerte, devise, récap"
```

---

### Task 3 : États attente / succès / timeout / erreur

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : État ATTENTE — supprimer cercle bleu**

Trouver le bloc `v-if="etape === 'attente'"`. Remplacer le div cercle :

```html
<!-- Avant -->
<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 mx-auto">
    <svg class="animate-spin w-8 h-8 text-[#1A56A0]" ...>

<!-- Après -->
<div class="mx-auto">
    <svg class="animate-spin w-8 h-8 text-gray-400" ...>
```

- [ ] **Step 2 : État SUCCÈS — supprimer cercle vert**

Trouver le bloc `v-if="etape === 'succes'"`. Remplacer :

```html
<!-- Avant -->
<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-50 mx-auto">
    <svg class="w-8 h-8 text-green-600" ...>

<!-- Après -->
<p class="text-4xl text-gray-900 mx-auto">✓</p>
```

(Supprimer le div+svg entier, le remplacer par ce paragraphe.)

- [ ] **Step 3 : État TIMEOUT — supprimer cercle jaune**

Trouver le bloc `v-if="etape === 'timeout'"`. Remplacer :

```html
<!-- Avant -->
<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-50 mx-auto">
    <svg class="w-8 h-8 text-yellow-500" ...>

<!-- Après -->
<p class="text-4xl text-gray-400 mx-auto">⏱</p>
```

- [ ] **Step 4 : État ERREUR — supprimer cercle rouge**

Trouver le bloc `v-if="etape === 'erreur'"`. Remplacer :

```html
<!-- Avant -->
<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto">
    <svg class="w-8 h-8 text-red-500" ...>

<!-- Après -->
<p class="text-4xl text-gray-400 mx-auto">✕</p>
```

- [ ] **Step 5 : Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "style(abonnement): supprimer icônes colorées états paiement"
```

---

### Task 4 : Historique — badges statut neutralisés

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : Supprimer `statusColor()` et simplifier `statusLabel()`**

Remplacer dans le script setup :

```typescript
// Avant
function statusLabel(status: string): string {
    return ({ pending: 'En attente', confirmed: 'Confirmé', expired: 'Expiré', failed: 'Échoué' })[status] ?? status
}

function statusColor(status: string): string {
    return ({
        pending:   'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-green-100 text-green-800',
        expired:   'bg-red-100 text-red-800',
        failed:    'bg-red-100 text-red-800',
    })[status] ?? 'bg-gray-100 text-gray-700'
}

// Après
function statusLabel(status: string): string {
    return ({
        pending:   'En attente',
        confirmed: '✓ Confirmé',
        expired:   'Expiré',
        failed:    '✕ Échoué',
    })[status] ?? status
}
```

- [ ] **Step 2 : Simplifier le badge dans le template**

Dans le tableau historique, remplacer la cellule statut :

```html
<!-- Avant -->
<span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusColor(s.status)]">
    {{ statusLabel(s.status) }}
</span>

<!-- Après -->
<span :class="['text-xs', s.status === 'confirmed' ? 'text-gray-700 font-medium' : 'text-gray-400']">
    {{ statusLabel(s.status) }}
</span>
```

- [ ] **Step 3 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir http://localhost et naviguer vers `/abonnement`. Vérifier :
- Cards plan : bordure grise (inactif), bordure noire épaisse (actif), aucun fond coloré
- Alerte expiration : texte gris, pas de fond jaune
- Boutons USD/CDF : bordure noire (actif), grise (inactif)
- Récap montant : juste une ligne de séparation
- Bouton "Payer" : toujours bleu `#1A56A0` ✓
- Historique : badges en texte simple

- [ ] **Step 4 : Commit final**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "style(abonnement): historique badges neutralisés — design minimaliste complet"
```
