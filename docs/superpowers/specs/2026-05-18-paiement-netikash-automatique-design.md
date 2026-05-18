# Paiement Netikash Automatique — Suppression flux manuel

> **For agentic workers:** Implement via `superpowers:subagent-driven-development`.

**Goal:** Remplacer le flux manuel (mailto + storeDemande) par une redirection vers le flux Netikash automatique déjà existant dans `/abonnement`.

**Architecture:** Modification de `Upgrade.vue` (redirection), `Abonnement/Index.vue` (lecture query param), `AbonnementController` (suppression storeDemande), `routes/web.php` (suppression route). Aucun changement au service Netikash.

---

## Contexte

Le flux automatique Netikash est déjà implémenté :
- `POST /abonnement/payer` → `AbonnementController@initierPaiement` → `InitierPaiementAction` → `NetikashService`
- Polling toutes les 5s sur `GET /api/abonnement/statut/{reference}`
- Webhook `POST /api/v1/payment/webhook` confirme l'abonnement

Le problème : `Upgrade.vue` appelle encore `contacter(plan)` qui ouvre un `mailto:`.

---

## Changements

### 1. `resources/js/Pages/Upgrade.vue`

**Supprimer** la fonction `contacter()` entièrement.

**Remplacer** les boutons "Passer au Premium/Pro" :

```vue
<!-- Avant -->
<button @click="contacter('premium')">Passer au Premium →</button>
<button @click="contacter('pro')">Passer au Pro →</button>

<!-- Après -->
<button @click="router.visit('/abonnement', { data: { plan: 'premium' } })">
  Passer au Premium →
</button>
<button @click="router.visit('/abonnement', { data: { plan: 'pro' } })">
  Passer au Pro →
</button>
```

**Supprimer** la section "Comment upgrader ?" (le bloc `<section>` contenant le `mailto:` et les instructions de paiement manuel).

**Simplification couleurs** (fait en même temps que D puisqu'on touche le fichier) :
- Supprimer les styles `text-gold`, `bg-gold`, `border-gold`, `.plan-card--featured` bleu
- `plan-card--featured` → même style que les autres cards, juste `border-2 border-gray-900`
- Supprimer le ruban "RECOMMANDÉ" bleu
- Badge "Premium" → `bg-gray-100 text-gray-700` (même que les autres)
- Prix Premium → `text-gray-900` (supprimer `text-blue-600`)
- Supprimer l'import de Google Fonts Playfair Display + DM Mono
- Badge feature bloquée → `bg-gray-100 text-gray-700` (supprimer `bg-blue-50 text-blue-600`)
- Bouton "Passer au Premium" → `bg-[#1A56A0] text-white` (seule couleur)
- Bouton "Passer au Pro" → `border-2 border-gray-900 text-gray-900`

### 2. `resources/js/Pages/Abonnement/Index.vue`

Lire le query param `plan` à l'arrivée pour pré-sélectionner :

```typescript
import { useRoute } from 'vue-router' // non — Inertia n'utilise pas vue-router
// Utiliser usePage() ou window.location.search

import { onMounted } from 'vue'

onMounted(() => {
    const params = new URLSearchParams(window.location.search)
    const planParam = params.get('plan')
    if (planParam === 'premium' || planParam === 'pro') {
        form.value.plan = planParam
    }
})
```

### 3. `app/Http/Controllers/AbonnementController.php`

**Supprimer** la méthode `storeDemande()` entièrement.

### 4. `routes/web.php`

**Supprimer** la route :
```php
Route::post('/abonnement/demande', [AbonnementController::class, 'storeDemande']);
```

---

## Ce qui NE change PAS

- `NetikashService`, `InitierPaiementAction`, `ConfirmerPaiementWebhookAction`
- Le polling côté Vue (`demarrerPolling`, `arreterPolling`)
- La route `POST /abonnement/payer`
- La route `GET /api/abonnement/statut/{reference}`
- Le webhook `POST /api/v1/payment/webhook`
