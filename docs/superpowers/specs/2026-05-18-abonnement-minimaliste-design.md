# Page Abonnement — Design Minimaliste

> **For agentic workers:** Implement via `superpowers:subagent-driven-development`.

**Goal:** Supprimer toutes les couleurs superflues de la page Abonnement/Index.vue pour n'en garder qu'une seule : le bleu PrimeGest `#1A56A0` sur le bouton CTA.

**Architecture:** Modification unique de `resources/js/Pages/Abonnement/Index.vue`. Aucun changement backend.

---

## Règle globale

- Fond : blanc
- Texte : `text-gray-900` (titres), `text-gray-600` (secondaire), `text-gray-400` (tertiaire)
- Bordures : `border-gray-200` (repos), `border-gray-900 border-2` (sélectionné/actif)
- Seule couleur : `bg-[#1A56A0]` sur le bouton "Payer" uniquement
- Aucun `bg-*-50`, `bg-*-100`, `text-*-800` coloré ailleurs

---

## Changements par section

### Cards de plan (Premium / Pro)

**Avant:**
```html
form.plan === 'premium' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'
form.plan === 'pro'     ? 'border-purple-500 bg-purple-50' : 'border-gray-200'
```
**Après:**
```html
form.plan === 'premium' ? 'border-2 border-gray-900' : 'border border-gray-200'
form.plan === 'pro'     ? 'border-2 border-gray-900' : 'border border-gray-200'
```
Supprimer aussi `text-blue-600` et `text-purple-600` sur les prix → `text-gray-900 font-bold`.

### Alerte expiration (joursWarning)

**Avant:** `bg-yellow-50 border border-yellow-200 text-yellow-800` avec icône `⚠`

**Après:** texte seul, pas de fond coloré :
```html
<p class="mt-2 text-sm text-gray-600">
  Expire dans <strong>{{ jours_restants }} jour(s)</strong> — pensez à renouveler.
</p>
```

### Badge plan actuel (planColor)

**Avant:** `bg-blue-100 text-blue-800` / `bg-purple-100 text-purple-800`

**Après:** toujours `bg-gray-100 text-gray-700` quelle que soit la valeur du plan. Supprimer `planColor` computed ou le simplifier à une seule valeur.

### Boutons devise (USD / CDF)

**Avant:** `border-[#1A56A0] bg-blue-50 text-[#1A56A0]` si actif

**Après:** `border-2 border-gray-900 text-gray-900` si actif, `border border-gray-200 text-gray-600` sinon.

### Récap montant

**Avant:** `bg-gray-50 rounded-lg px-4 py-3`

**Après:** `border-t border-gray-200 pt-3` — ligne de séparation, pas de fond.

### États : Attente / Succès / Timeout / Erreur

Supprimer tous les ronds colorés (`bg-blue-50`, `bg-green-50`, `bg-yellow-50`, `bg-red-50`).

**Attente:** spinner sans fond coloré, texte gris.

**Succès:**
```html
<p class="text-2xl">✓</p>
<h3>Paiement confirmé</h3>
```
Pas de cercle vert.

**Timeout / Erreur:** même principe — icône monochrome, texte gris foncé, pas de fond coloré.

Bouton "Voir mon plan" / "Réessayer" → `bg-[#1A56A0] text-white` (seule couleur conservée).

### Historique — badges statut

**Avant:** `bg-yellow-100 text-yellow-800`, `bg-green-100 text-green-800`, etc.

**Après:** texte préfixé, couleur neutre :
- `confirmed` → `✓ Confirmé` en `text-gray-700`
- `pending`   → `En attente` en `text-gray-500`
- `expired`   → `Expiré` en `text-gray-400`
- `failed`    → `✗ Échoué` en `text-gray-600`

Supprimer la fonction `statusColor()` ou la remplacer par une valeur constante.

---

## Ce qui NE change PAS

- Structure HTML / layout (espacements, grid, shadow)
- Logique métier (payer, polling, recommencer, recharger)
- Le bouton "Payer" : `bg-[#1A56A0] hover:bg-[#0B2D5E]` conservé
- Les messages d'erreur texte (`erreurMsg`)
