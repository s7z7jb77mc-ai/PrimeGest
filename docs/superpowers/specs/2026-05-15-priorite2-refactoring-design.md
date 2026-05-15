# Priorité 2 — Refactoring Architecture PrimeGest
**Date :** 2026-05-15
**Approche :** Option C — Stack complète CLAUDE.md
**Stratégie :** TDD — tests écrits avant le code
**Ordre :** MouvementStock → Transferts → Caisse → Global

---

## 1. Objectif

Mettre les controllers critiques en conformité avec l'architecture définie dans CLAUDE.md :
- Extraire toute logique métier dans des classes Action dédiées
- Valider les entrées via Form Requests (plus de `$request->validate()` inline)
- Réduire chaque controller à ≤ 20 lignes
- Ajouter `declare(strict_types=1)` sur tous les fichiers PHP
- Ajouter `->paginate(50)` sur toutes les listes sans limite

**Périmètre :** `MouvementStockController` (579 lignes), `TransfertController` (537 lignes), `CaisseController` (134 lignes), plus les changements globaux.

---

## 2. Architecture cible

```
Request HTTP
    ↓
[Controller]    — délègue, retourne (≤ 20 lignes)
    ↓
[Form Request]  — validation, messages d'erreur
    ↓
[Action]        — une classe = une opération métier
    ↓
[Service]       — logique réutilisable (MouvementStockService, CaisseService)
    ↓
[Model / DB]
```

---

## 3. Dossiers et fichiers à créer

```
app/
├── Actions/
│   ├── MouvementStock/
│   │   ├── CreerMouvementAction.php
│   │   ├── GenererFactureVenteAction.php
│   │   ├── AnnulerMouvementAction.php
│   │   ├── CreerBonEntreeAction.php
│   │   └── GenererFactureBonEntreeAction.php
│   ├── Transferts/
│   │   ├── CreerTransfertCaisseAction.php
│   │   ├── CreerTransfertStockAction.php
│   │   └── ApprouverTransfertAction.php
│   └── Caisse/
│       ├── CreerMouvementCaisseAction.php
│       └── InitialiserCaisseAction.php
└── Http/
    └── Requests/
        ├── MouvementStock/
        │   ├── CreerMouvementRequest.php
        │   └── CreerBonEntreeRequest.php
        ├── Transferts/
        │   ├── CreerTransfertCaisseRequest.php
        │   ├── CreerTransfertStockRequest.php
        │   └── ApprouverTransfertRequest.php
        └── Caisse/
            ├── CreerMouvementCaisseRequest.php
            └── InitialiserCaisseRequest.php

tests/
└── Feature/
    └── Actions/
        ├── CreerMouvementActionTest.php
        ├── GenererFactureVenteActionTest.php
        ├── AnnulerMouvementActionTest.php
        ├── CreerTransfertCaisseActionTest.php
        ├── CreerTransfertStockActionTest.php
        ├── ApprouverTransfertActionTest.php
        └── CreerMouvementCaisseActionTest.php
```

---

## 4. Module MouvementStock

### 4.1 Actions

**`CreerMouvementAction`**
- Inputs : données validées (type, produit_id, quantite, prix_unitaire, payment_type, commentaire, client_phone, fournisseur_id, use_reduction), entreprise_id, succursale_id
- Orchestration : appelle `MouvementStockService` + `MouvementStockWorkflowService`
- Effets : crée le `MouvementStock`, met à jour `Stock`, crédite/débite `Caisse`, crée `Creance` si credit
- Retourne : le `MouvementStock` créé

**`GenererFactureVenteAction`**
- Inputs : mouvement_id, entreprise_id
- Orchestration : vérifie que c'est une sortie, génère la `Facture` avec ses lignes
- Retourne : la `Facture` créée

**`AnnulerMouvementAction`**
- Inputs : MouvementStock, user
- Orchestration : reverse le stock, reverse la caisse, marque le mouvement annulé
- Retourne : void

**`CreerBonEntreeAction`**
- Inputs : données validées, entreprise_id, succursale_id
- Orchestration : crée le `BonEntree` + `BonEntreeLigne[]`, met à jour le stock
- Retourne : le `BonEntree` créé

**`GenererFactureBonEntreeAction`**
- Inputs : bon_entree_id, entreprise_id
- Retourne : la `Facture` créée

### 4.2 Form Requests

**`CreerMouvementRequest`**
```php
rules(): [
    'type'           => ['required', 'in:entree,sortie'],
    'produit_id'     => ['required', 'integer', 'exists:produits,id'],
    'quantite'       => ['required', 'integer', 'min:1'],
    'prix_unitaire'  => ['nullable', 'numeric', 'min:0'],
    'payment_type'   => ['nullable', 'in:cash,credit,reduction'],
    'commentaire'    => ['nullable', 'string', 'max:500'],
    'client_phone'   => ['nullable', 'string', 'max:20'],
    'fournisseur_id' => ['nullable', 'integer', 'exists:fournisseurs,id'],
    'use_reduction'  => ['boolean'],
]
```

### 4.3 Controller résultant (≤ 20 lignes)

```php
public function store(CreerMouvementRequest $request): RedirectResponse
{
    $action = new CreerMouvementAction(
        app(MouvementStockService::class),
        app(MouvementStockWorkflowService::class),
    );

    $action->execute(
        $request->validated(),
        auth()->user()->entreprise_id,
        session('succursale_id'),
    );

    return redirect()->back()->with('success', 'Mouvement enregistré.');
}
```

---

## 5. Module Transferts

### 5.1 Actions

**`CreerTransfertCaisseAction`**
- Inputs : données validées (from_succursale_id, to_succursale_id, montant, password)
- Sécurité : `Hash::check($password, $user->password)` — lève exception si échec
- Orchestration : débite caisse source, crédite caisse destination, crée `Transfert`, écrit dans `Journal`
- Retourne : le `Transfert` créé

**`CreerTransfertStockAction`**
- Inputs : données validées (produit_id, quantite, from_succursale_id, to_succursale_id, password)
- Sécurité : idem — `Hash::check` obligatoire
- Orchestration : décrémente stock source, incrémente stock destination, crée `Transfert`
- Retourne : le `Transfert` créé

**`ApprouverTransfertAction`**
- Inputs : `Transfert`, statut (approved/rejected), user
- Règle : seul super_admin ou manager de la succursale destination peut approuver
- Retourne : le `Transfert` mis à jour

### 5.2 Form Requests

**`CreerTransfertCaisseRequest`** — valide from/to/montant/password (required)
**`CreerTransfertStockRequest`** — valide produit_id/quantite/from/to/password (required)
**`ApprouverTransfertRequest`** — valide statut (in:approved,rejected)

---

## 6. Module Caisse

### 6.1 Actions

**`CreerMouvementCaisseAction`**
- Inputs : données validées (type entree/sortie, montant, description)
- Orchestration : délègue à `CaisseService::enregistrer()`
- Retourne : le `Caisse` créé

**`InitialiserCaisseAction`**
- Inputs : montant_initial, entreprise_id, succursale_id
- Condition : ne peut être exécutée qu'une seule fois (vérifie qu'aucune caisse n'existe)
- Retourne : la `Caisse` initialisée

---

## 7. Changes globales

### 7.1 `declare(strict_types=1)`
Ajouté en tête de **tous** les fichiers PHP du projet :
- `app/Http/Controllers/*.php` (tous)
- `app/Models/*.php` (tous)
- `app/Services/*.php` (tous)
- `app/Traits/*.php` (tous)
- `app/Jobs/*.php` (tous)

### 7.2 Pagination
Remplacer `->get()` par `->paginate(50)` sur :
- `TransfertController::index()`
- `ClientController::index()`
- `FournisseurController::index()`
- `ProduitController::index()`
- `MouvementStockController::index()` (mouvements récents)

Le frontend Inertia reçoit déjà les métadonnées de pagination via `$page->props` — aucune modification Vue nécessaire si on utilise `->through()` pour la transformation.

---

## 8. Stratégie TDD

### Ordre d'exécution par Action :
1. Écrire le test Feature (`tests/Feature/Actions/NomActionTest.php`)
2. Lancer le test → RED (il échoue car l'Action n'existe pas)
3. Créer la Form Request
4. Créer l'Action
5. Brancher dans le Controller
6. Lancer le test → GREEN
7. Refactorer si nécessaire → GREEN maintenu

### Template de test :
```php
class CreerMouvementActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_mouvement_sortie_met_a_jour_stock(): void
    {
        $user    = User::factory()->superAdmin()->create();
        $produit = Produit::factory()->for($user->entreprise)->withStock(10)->create();

        $this->actingAs($user)
             ->post('/mouvement-stocks', [
                 'type'          => 'sortie',
                 'produit_id'    => $produit->id,
                 'quantite'      => 3,
                 'payment_type'  => 'cash',
                 'commentaire'   => 'Vente test',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('mouvement_stocks', ['type' => 'sortie', 'quantite' => 3]);
        $this->assertDatabaseHas('stocks', ['produit_id' => $produit->id, 'quantite' => 7]);
    }
}
```

---

## 9. Contraintes et règles

- Jamais de logique dans le controller — tout dans l'Action
- Les Actions ne connaissent pas `Request` — elles reçoivent des données brutes validées
- Les Actions ne retournent pas de réponses HTTP — elles retournent des entités
- `Hash::check()` pour les transferts reste dans l'Action (pas dans le controller)
- Les Services existants (`MouvementStockService`, `CaisseService`) ne sont pas réécrits — les Actions les appellent
- `SyncObservable` déclenche automatiquement sur chaque création/modification dans les Actions

---

## 10. Ordre d'implémentation

```
Phase 1 — MouvementStock
  1.1  CreerMouvementRequest + test + CreerMouvementAction + controller slim
  1.2  GenererFactureVenteAction (test + action + controller)
  1.3  AnnulerMouvementAction (test + action + controller)
  1.4  CreerBonEntreeAction + GenererFactureBonEntreeAction

Phase 2 — Transferts
  2.1  CreerTransfertCaisseAction (test + request + action + controller)
  2.2  CreerTransfertStockAction (test + request + action + controller)
  2.3  ApprouverTransfertAction (test + request + action + controller)

Phase 3 — Caisse
  3.1  CreerMouvementCaisseAction (test + request + action + controller)
  3.2  InitialiserCaisseAction (test + request + action + controller)

Phase 4 — Global
  4.1  declare(strict_types=1) sur tous les fichiers PHP
  4.2  ->paginate(50) sur toutes les listes sans limite
```
