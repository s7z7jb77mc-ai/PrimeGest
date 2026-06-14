# Spec — Création batch de produits avec stock initial

**Date :** 2026-06-14
**Statut :** Approuvée

---

## 1. Contexte

La page Produits permet aujourd'hui de créer un produit à la fois via un modal. Pour les nouvelles entreprises qui doivent saisir un catalogue complet, entrer le mot de passe Super Admin pour chaque produit est une friction importante.

Cette spec couvre :
- L'ajout d'un champ **Stock Initial** (write-once) au formulaire de création
- Un nouveau flux **batch** : l'utilisateur compose une liste de produits côté client, puis confirme tout en une seule saisie de mot de passe

---

## 2. Comportement attendu

### 2.1 Page en état normal

```
[Recherche...]              [Dashboard]  [Ajouter produit]

┌── Tableau produits existants ───────────────────────────┐
│ Nom │ Stock │ Prix achat │ Prix vente │ Seuil │ Action  │
│ ... │  ...  │    ...     │    ...     │  ...  │ Modifier│
└─────────────────────────────────────────────────────────┘
```

### 2.2 Après clic sur "Ajouter produit"

Le bouton toggle une section qui s'insère entre le header et le tableau existant.

```
[Recherche...]              [Dashboard]  [Ajouter produit]

┌── Formulaire de saisie ─────────────────────────────────┐
│ Nom*  │ Prix achat │ Prix vente │ Seuil │ Stock Initial  │
│                                         [Ajouter produit]│
└─────────────────────────────────────────────────────────┘

┌── Produits en attente (visible si ≥ 1 produit) ─────────┐
│ Nom │ P.achat │ P.vente │ Seuil │ Stock initial │  ✕    │
├─────────────────────────────────────────────────────────┤
│ Doliprane  │ 100 │ 150 │  5 │  20  │  ✕                 │
│ Ibuprofène │  80 │ 120 │  3 │  10  │  ✕                 │
├─────────────────────────────────────────────────────────┤
│ [Mot de passe Super Admin ........] [Créer les produits] │
└─────────────────────────────────────────────────────────┘

┌── Tableau produits existants (inchangé) ────────────────┐
│ ...                                                     │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Règles métier

### Stock Initial
- Champ présent **uniquement** à la création, absent du formulaire d'édition
- Valeur par défaut : **0**
- Saisie libre : entier ≥ 0
- À la création, initialise `stocks.quantite` pour la succursale active (ou centrale)
- Write-once : aucun endpoint ne permet de modifier ce champ après création ; `stocks.quantite` évolue ensuite via les mouvements de stock (ventes, achats)

### Validation locale (avant ajout à la liste)
- `nom` : requis, non vide
- `prix_achat` : numérique ≥ 0
- `prix_vente` : numérique ≥ 0
- `seuil_stock` : entier ≥ 0, défaut 0
- `stock_initial` : entier ≥ 0, défaut 0
- Erreurs affichées inline sous chaque champ ; si invalide, le produit n'est pas ajouté à la liste

### Confirmation batch
- Le mot de passe est saisi une seule fois pour confirmer toute la liste
- Si le mot de passe est incorrect : erreur affichée, liste en attente préservée (rien n'est perdu)
- Si le batch réussit : liste vidée, formulaire réinitialisé, section masquée, tableau principal rechargé

---

## 4. Architecture technique

### 4.1 Frontend — `resources/js/Pages/Produits/Index.vue`

**Nouveaux états réactifs :**
```ts
const showForm       = ref(false)           // toggle section formulaire
const pendingProduits = ref<PendingProduit[]>([])  // liste en mémoire
const batchPassword  = ref('')              // mot de passe unique

interface PendingProduit {
  nom: string
  prix_achat: number
  prix_vente: number
  seuil_stock: number
  stock_initial: number
}
```

**Formulaire local (réinitalisé après chaque ajout) :**
```ts
const newProduit = ref<PendingProduit>({
  nom: '', prix_achat: 0, prix_vente: 0, seuil_stock: 0, stock_initial: 0
})
```

**Fonctions clés :**
- `toggleForm()` — affiche/masque la section, réinitialise le formulaire local
- `addToPending()` — valide localement, pousse dans `pendingProduits`, réinitialise `newProduit`
- `removeFromPending(index)` — retire un produit de la liste
- `confirmBatch()` — `router.post('/produits/batch', { produits: pendingProduits.value, admin_password: batchPassword.value })` ; après succès : vide la liste, masque la section

**Formulaire d'édition :** modal existant inchangé — `stock_initial` absent.

### 4.2 Backend — `ProductController`

**Nouvelle méthode `storeBatch(Request $request)` :**
```
POST /produits/batch
middleware : ['auth', 'plan:produits']
```

Validation :
```php
$request->validate([
    'admin_password'           => 'required|string',
    'produits'                 => 'required|array|min:1',
    'produits.*.nom'           => 'required|string|max:255',
    'produits.*.prix_achat'    => 'required|numeric|min:0',
    'produits.*.prix_vente'    => 'required|numeric|min:0',
    'produits.*.seuil_stock'   => 'nullable|integer|min:0',
    'produits.*.stock_initial' => 'nullable|integer|min:0',
]);
```

Logique :
1. Vérifier `Hash::check($request->admin_password, $user->password)` — sinon `ValidationException`
2. Ouvrir une transaction DB
3. Pour chaque produit : `Produit::create(...)` puis `Stock::firstOrCreate(...)` avec `quantite = stock_initial ?? 0`
4. Commit — retourner `redirect()->route('produits.index')->with('success', ...)`

**Route à ajouter dans `web.php` :**
```php
Route::post('/produits/batch', [ProductController::class, 'storeBatch'])
    ->middleware(['auth', 'plan:produits'])
    ->name('produits.batch');
```

> ⚠️ Cette route doit être déclarée **avant** `Route::resource('produits', ...)` pour éviter que `/produits/batch` soit capturé comme `produits.show`.

**Méthode `update()` :** inchangée. `stock_initial` n'est pas dans les règles de validation — protection write-once garantie par omission.

### 4.3 Base de données

Aucune migration nécessaire. `stock_initial` est une valeur transitoire utilisée uniquement pour initialiser `stocks.quantite` à la création.

---

## 5. Gestion des erreurs

| Situation | Comportement |
|---|---|
| Champ requis vide (frontend) | Message inline sous le champ, ajout bloqué |
| Mot de passe vide | Bouton "Créer" désactivé |
| Mot de passe incorrect (serveur) | Erreur affichée, liste en attente préservée |
| Échec partiel dans le batch | Rollback transaction entière, aucun produit créé, message d'erreur |
| Mode hors-ligne | Flux batch désactivé (bouton grisé) ; création individuelle offline inchangée |

---

## 6. Fichiers modifiés

| Fichier | Nature |
|---|---|
| `resources/js/Pages/Produits/Index.vue` | Refonte section création |
| `app/Http/Controllers/ProductController.php` | Ajout méthode `storeBatch()` |
| `routes/web.php` | Ajout route `POST /produits/batch` |

Aucune migration, aucun nouveau modèle.
