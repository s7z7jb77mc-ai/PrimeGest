# Refactoring Architecture PrimeGest — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extraire toute la logique métier de `MouvementStockController` (579 lignes), `TransfertController` (537 lignes) et `CaisseController` (134 lignes) dans des classes Action + FormRequest dédiées, réduisant chaque controller à ≤ 20 lignes.

**Architecture:** Controller → reçoit, délègue, retourne. FormRequest → valide. Action → logique métier. Les Services existants (`MouvementStockService`, `CaisseService`) sont appelés par les Actions sans être réécrits.

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit + SQLite in-memory pour les tests. Les tests utilisent `RefreshDatabase` et créent les données manuellement (pas de factories).

---

## Carte des fichiers

**Créer :**
```
app/Http/Requests/MouvementStock/
    CreerMouvementRequest.php
    GenererFactureVenteRequest.php
    CreerBonEntreeRequest.php

app/Actions/MouvementStock/
    CreerMouvementAction.php
    GenererFactureVenteAction.php
    CreerBonEntreeAction.php

app/Http/Requests/Transferts/
    StoreCaisseTransfertRequest.php
    StoreStockTransfertRequest.php
    ApprouverTransfertRequest.php

app/Actions/Transferts/
    CreerTransfertCaisseAction.php
    CreerTransfertStockAction.php
    ApprouverTransfertAction.php

app/Http/Requests/Caisse/
    InitialiserCaisseRequest.php

app/Actions/Caisse/
    InitialiserCaisseAction.php

tests/Feature/Actions/
    CreerMouvementActionTest.php
    GenererFactureVenteActionTest.php
    CreerBonEntreeActionTest.php
    TransfertActionTest.php
    InitialiserCaisseActionTest.php
```

**Modifier :**
```
app/Http/Controllers/MouvementStockController.php  — slim + strict_types
app/Http/Controllers/TransfertController.php       — slim + strict_types
app/Http/Controllers/CaisseController.php          — slim + strict_types
app/Http/Controllers/*.php (tous)                  — strict_types
app/Models/*.php (tous)                            — strict_types
app/Services/*.php (tous)                          — strict_types
app/Traits/*.php (tous)                            — strict_types
app/Jobs/*.php (tous)                              — strict_types
```

---

## PHASE 1 — MouvementStock

---

### Task 1 : CreerMouvementRequest + CreerMouvementAction

**Files:**
- Create: `app/Http/Requests/MouvementStock/CreerMouvementRequest.php`
- Create: `app/Actions/MouvementStock/CreerMouvementAction.php`
- Modify: `app/Http/Controllers/MouvementStockController.php` (méthode `store()`)
- Test: `tests/Feature/Actions/CreerMouvementActionTest.php`

---

- [ ] **Step 1 : Écrire le test Feature (avant tout code)**

Créer `tests/Feature/Actions/CreerMouvementActionTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Models\Entreprise;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreerMouvementActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $entreprise = Entreprise::create([
            'name' => 'Test SA',
            'plan' => 'premium',
        ]);

        $this->user = User::create([
            'name'          => 'Super Admin',
            'email'         => 'admin@test.com',
            'password'      => bcrypt('password'),
            'role'          => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $this->produit = Produit::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Aspirine',
            'prix_achat'    => 500,
            'prix_vente'    => 1000,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $entreprise->id,
            'produit_id'    => $this->produit->id,
            'quantite'      => 20,
            'prix_achat'    => 500,
            'prix_vente'    => 1000,
            'total_achat'   => 10000,
            'total_vente'   => 20000,
            'seuil_stock'   => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_vente_cash_decremente_stock(): void
    {
        $this->actingAs($this->user)
            ->post('/mouvement-stocks', [
                'produit_id'   => $this->produit->id,
                'type'         => 'sortie',
                'quantite'     => 5,
                'prix_unitaire'=> 1000,
                'payment_type' => 'cash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mouvement_stocks', [
            'produit_id' => $this->produit->id,
            'type'       => 'sortie',
            'quantite'   => 5,
        ]);

        $this->assertEquals(15, DB::table('stocks')
            ->where('produit_id', $this->produit->id)
            ->value('quantite'));
    }

    public function test_entree_incremente_stock(): void
    {
        $this->actingAs($this->user)
            ->post('/mouvement-stocks', [
                'produit_id'   => $this->produit->id,
                'type'         => 'entree',
                'quantite'     => 10,
                'prix_unitaire'=> 500,
                'payment_type' => 'cash',
            ])
            ->assertRedirect();

        $this->assertEquals(30, DB::table('stocks')
            ->where('produit_id', $this->produit->id)
            ->value('quantite'));
    }
}
```

- [ ] **Step 2 : Lancer le test — vérifier qu'il passe (il teste le comportement existant)**

```bash
cd "/Users/trystanbyabuze/Desktop/PrimeGest "
php artisan test --filter CreerMouvementActionTest
```

Le test doit passer en VERT (le contrôleur existant fait déjà le travail).

- [ ] **Step 3 : Créer CreerMouvementRequest**

Créer `app/Http/Requests/MouvementStock/CreerMouvementRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\MouvementStock;

use Illuminate\Foundation\Http\FormRequest;

class CreerMouvementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'produit_id'     => ['required', 'exists:produits,id'],
            'type'           => ['required', 'in:entree,sortie'],
            'quantite'       => ['required', 'integer', 'min:1'],
            'prix_unitaire'  => ['nullable', 'numeric', 'min:0'],
            'commentaire'    => ['nullable', 'string', 'max:1000'],
            'payment_type'   => ['nullable', 'in:cash,credit,reduction'],
            'use_reduction'  => ['nullable', 'boolean'],
            'client_phone'   => ['nullable', 'string', 'max:30'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
        ];
    }
}
```

- [ ] **Step 4 : Créer CreerMouvementAction**

Créer `app/Actions/MouvementStock/CreerMouvementAction.php` en déplaçant TOUTE la logique de `MouvementStockController::store()` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\MouvementStock;

use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\ReductionUsage;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreerMouvementAction
{
    public function __construct(private readonly MouvementStockService $service) {}

    public function execute(array $data, int $entrepriseId, ?int $succursaleId): MouvementStock
    {
        $produit         = Produit::where('entreprise_id', $entrepriseId)->findOrFail($data['produit_id']);
        $parametres      = Parametre::where('entreprise_id', $entrepriseId)->first();
        $tauxReduction   = (float) ($parametres?->reduction_accordee ?? 0);
        $hasClients      = Schema::hasColumn('clients', 'succursale_id');
        $hasFournisseurs = Schema::hasColumn('fournisseurs', 'succursale_id');

        return DB::transaction(function () use ($data, $entrepriseId, $produit, $tauxReduction, $succursaleId, $hasClients, $hasFournisseurs): MouvementStock {
            $useReduction      = (bool) ($data['use_reduction'] ?? false);
            $paymentType       = $useReduction ? 'reduction' : ($data['payment_type'] ?? 'cash');
            $prixUnitaireFinal = $data['prix_unitaire'] ?? ($data['type'] === 'entree' ? $produit->prix_achat : $produit->prix_vente);
            $total             = (int) $data['quantite'] * (float) $prixUnitaireFinal;
            $clientUpdated     = false;
            $fournisseurMaj    = false;
            $commentaire       = $data['commentaire'] ?? null;

            if ($data['type'] === 'sortie' && trim((string) $commentaire) === '') {
                $commentaire = 'Vente - ' . $produit->nom;
            }

            if ($useReduction && ($data['payment_type'] ?? null) === 'credit') {
                throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
            }

            // ── Réduction sortie ───────────────────────────────────────
            if ($useReduction && $data['type'] === 'sortie') {
                $clientPhone = trim((string) ($data['client_phone'] ?? ''));
                if ($clientPhone === '') {
                    throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour utiliser la réduction.']);
                }
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (! $client) {
                    throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
                }
                $reductionDispo = (float) $client->reduction_accordee;
                $reductionUsed  = min($total, $reductionDispo);
                $resteAPayer    = $total - $reductionUsed;
                $client->reduction_accordee = max($reductionDispo - $reductionUsed, 0);
                $client->achat_mensuel      = 0;
                $client->save();
                $clientUpdated = true;
                $this->recordReductionUsage($entrepriseId, 'client', $client->id, $reductionUsed, (float) $client->reduction_accordee, $succursaleId);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Vente (réduction) : {$produit->nom}", 'date_operation' => now(), 'entree' => $resteAPayer, 'sortie' => 0];
                    if (Schema::hasColumn('caisses', 'type_operation')) {
                        $caisseData['type_operation'] = 'auto';
                    }
                    CaisseService::createOperation($caisseData);
                }
            }

            // ── Réduction entrée ───────────────────────────────────────
            if ($useReduction && $data['type'] === 'entree') {
                $fournisseurId = $data['fournisseur_id'] ?? null;
                if (! $fournisseurId) {
                    throw ValidationException::withMessages(['fournisseur_id' => 'Fournisseur requis pour utiliser la réduction.']);
                }
                $fournisseur    = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->findOrFail($fournisseurId);
                $reductionDispo = (float) $fournisseur->reduction_obtenue;
                $reductionUsed  = min($total, $reductionDispo);
                $resteAPayer    = $total - $reductionUsed;
                $fournisseur->reduction_obtenue = max($reductionDispo - $reductionUsed, 0);
                $fournisseur->achat_mensuel     = 0;
                $fournisseur->save();
                $fournisseurMaj = true;
                $this->recordReductionUsage($entrepriseId, 'fournisseur', $fournisseur->id, $reductionUsed, (float) $fournisseur->reduction_obtenue, $succursaleId);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Achat (réduction) : {$produit->nom}", 'date_operation' => now(), 'entree' => 0, 'sortie' => $resteAPayer];
                    if (Schema::hasColumn('caisses', 'type_operation')) {
                        $caisseData['type_operation'] = 'auto';
                    }
                    CaisseService::createOperation($caisseData);
                }
            }

            // ── Crédit sortie ──────────────────────────────────────────
            if ($paymentType === 'credit' && $data['type'] === 'sortie') {
                $clientPhone = trim((string) ($data['client_phone'] ?? ''));
                if ($clientPhone === '') {
                    throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour une vente à crédit.']);
                }
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (! $client) {
                    throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
                }
                $client->creance = (float) $client->creance + $total;
                $this->updateClientStats($client, $total, $tauxReduction);
                $client->save();
                $clientUpdated = true;
            }

            // ── Crédit entrée ──────────────────────────────────────────
            if ($paymentType === 'credit' && $data['type'] === 'entree') {
                $fournisseurId = $data['fournisseur_id'] ?? null;
                if (! $fournisseurId) {
                    throw ValidationException::withMessages(['fournisseur_id' => 'Fournisseur requis pour un achat à crédit.']);
                }
                $fournisseur       = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->findOrFail($fournisseurId);
                $fournisseur->dette = (float) $fournisseur->dette + $total;
                $this->updateFournisseurStats($fournisseur, $total);
                $fournisseur->save();
                $fournisseurMaj = true;
            }

            // ── Stats client cash (si non déjà maj) ───────────────────
            if ($data['type'] === 'sortie' && ! empty($data['client_phone']) && ! $clientUpdated) {
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', trim((string) $data['client_phone']))->first();
                if ($client) {
                    if (! $useReduction) {
                        $this->updateClientStats($client, $total, $tauxReduction);
                    }
                    $client->save();
                }
            }

            // ── Stats fournisseur cash (si non déjà maj) ──────────────
            if ($data['type'] === 'entree' && ! empty($data['fournisseur_id']) && ! $fournisseurMaj) {
                $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->find($data['fournisseur_id']);
                if ($fournisseur) {
                    if (! $useReduction) {
                        $this->updateFournisseurStats($fournisseur, $total);
                    }
                    $fournisseur->save();
                }
            }

            return $this->service->record(
                $entrepriseId,
                $produit,
                $data['type'],
                (int) $data['quantite'],
                $data['prix_unitaire'] ?? null,
                $commentaire,
                auth()->id(),
                $paymentType,
                $succursaleId,
            );
        });
    }

    private function updateClientStats(Client $client, float $montant, float $tauxReduction): void
    {
        $client->achat_mensuel      = (float) $client->achat_mensuel + $montant;
        $client->reduction_accordee = $tauxReduction > 0
            ? round($client->achat_mensuel * ($tauxReduction / 100), 2)
            : 0;
    }

    private function updateFournisseurStats(Fournisseur $fournisseur, float $montant): void
    {
        $fournisseur->achat_mensuel     = (float) $fournisseur->achat_mensuel + $montant;
        $taux                           = (float) $fournisseur->reduction_pourcentage;
        $fournisseur->reduction_obtenue = $taux > 0
            ? round($fournisseur->achat_mensuel * ($taux / 100), 2)
            : 0;
    }

    private function recordReductionUsage(int $entrepriseId, string $entityType, int $entityId, float $utilise, float $reste, ?int $succursaleId): void
    {
        $payload = ['entreprise_id' => $entrepriseId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'montant_utilise' => $utilise, 'reste_apres' => $reste];
        if (Schema::hasColumn('reduction_usages', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        ReductionUsage::create($payload);
    }
}
```

- [ ] **Step 5 : Brancher dans le controller — remplacer store()**

Dans `app/Http/Controllers/MouvementStockController.php`, remplacer la méthode `store()` complète et supprimer les méthodes privées `updateClientStats`, `updateFournisseurStats`, `recordReductionUsage` (elles vivent maintenant dans l'Action). Ajouter `declare(strict_types=1)` en tête de fichier :

```php
// Imports à ajouter en haut :
use App\Actions\MouvementStock\CreerMouvementAction;
use App\Http\Requests\MouvementStock\CreerMouvementRequest;
use Illuminate\Http\RedirectResponse;

// Remplacer store() par :
public function store(CreerMouvementRequest $request): RedirectResponse
{
    (new CreerMouvementAction(app(MouvementStockService::class)))->execute(
        $request->validated(),
        $request->user()->entreprise_id,
        session('succursale_id'),
    );

    return redirect()->route('mouvement-stocks.index');
}
```

Supprimer aussi de `MouvementStockController` :
- la méthode privée `appliquerMouvementStock()` (l'Action appelle le service directement)
- les méthodes privées `updateClientStats()`, `updateFournisseurStats()`, `recordReductionUsage()`

- [ ] **Step 6 : Relancer le test — doit rester VERT**

```bash
php artisan test --filter CreerMouvementActionTest
```

Expected: `2 tests, 2 assertions` — PASS.

- [ ] **Step 7 : Commit**

```bash
git add app/Http/Requests/MouvementStock/CreerMouvementRequest.php \
        app/Actions/MouvementStock/CreerMouvementAction.php \
        app/Http/Controllers/MouvementStockController.php \
        tests/Feature/Actions/CreerMouvementActionTest.php
git commit -m "refactor(mouvement-stock): extraire store() dans CreerMouvementAction + FormRequest"
```

---

### Task 2 : GenererFactureVenteRequest + GenererFactureVenteAction

**Files:**
- Create: `app/Http/Requests/MouvementStock/GenererFactureVenteRequest.php`
- Create: `app/Actions/MouvementStock/GenererFactureVenteAction.php`
- Modify: `app/Http/Controllers/MouvementStockController.php` (méthode `genererFactureVente()` + supprimer `archiverFacture`)
- Test: `tests/Feature/Actions/GenererFactureVenteActionTest.php`

---

- [ ] **Step 1 : Écrire le test Feature**

Créer `tests/Feature/Actions/GenererFactureVenteActionTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenererFactureVenteActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $entreprise = Entreprise::create(['name' => 'Test SA', 'plan' => 'premium']);

        $this->user = User::create([
            'name' => 'Admin', 'email' => 'a@test.com',
            'password' => bcrypt('pass'), 'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $this->produit = Produit::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Paracétamol', 'prix_achat' => 200, 'prix_vente' => 500,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $entreprise->id, 'produit_id' => $this->produit->id,
            'quantite' => 100, 'prix_achat' => 200, 'prix_vente' => 500,
            'total_achat' => 20000, 'total_vente' => 50000, 'seuil_stock' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_genere_facture_avec_plusieurs_lignes(): void
    {
        $this->actingAs($this->user)
            ->post('/mouvement-stocks/generer-facture', [
                'lignes' => [
                    ['produit_id' => $this->produit->id, 'quantite' => 2, 'prix_unitaire' => 500],
                    ['produit_id' => $this->produit->id, 'quantite' => 3, 'prix_unitaire' => 500],
                ],
                'payment_type' => 'cash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('factures', [
            'entreprise_id' => $this->user->entreprise_id,
        ]);

        $facture = Facture::where('entreprise_id', $this->user->entreprise_id)->latest()->first();
        $this->assertEquals(2500, $facture->total_ttc);
        $this->assertCount(2, $facture->lignes);
    }
}
```

- [ ] **Step 2 : Lancer le test — doit passer (comportement existant)**

```bash
php artisan test --filter GenererFactureVenteActionTest
```

Expected: PASS.

- [ ] **Step 3 : Créer GenererFactureVenteRequest**

Créer `app/Http/Requests/MouvementStock/GenererFactureVenteRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\MouvementStock;

use Illuminate\Foundation\Http\FormRequest;

class GenererFactureVenteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.produit_id'    => ['required', 'exists:produits,id'],
            'lignes.*.quantite'      => ['required', 'integer', 'min:1'],
            'lignes.*.prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'payment_type'           => ['nullable', 'in:cash,credit,reduction'],
            'use_reduction'          => ['nullable', 'boolean'],
            'client_phone'           => ['nullable', 'string', 'max:30'],
        ];
    }
}
```

- [ ] **Step 4 : Créer GenererFactureVenteAction**

Créer `app/Actions/MouvementStock/GenererFactureVenteAction.php` en déplaçant TOUTE la logique de `genererFactureVente()` + la méthode privée `archiverFacture()` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\MouvementStock;

use App\Models\Archive;
use App\Models\Client;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\ReductionUsage;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use App\Support\SuccursaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GenererFactureVenteAction
{
    public function __construct(private readonly MouvementStockService $service) {}

    public function execute(array $data, int $entrepriseId, ?int $succursaleId, int $userId): Facture
    {
        $parametres    = Parametre::where('entreprise_id', $entrepriseId)->first();
        $tva           = (float) ($parametres?->tva ?? 0);
        $tauxReduction = (float) ($parametres?->reduction_accordee ?? 0);
        $hasClients    = Schema::hasColumn('clients', 'succursale_id');

        $facture = DB::transaction(function () use ($data, $entrepriseId, $succursaleId, $userId, $tva, $tauxReduction, $hasClients): Facture {
            $totalTtc     = 0.0;
            $totalHt      = 0.0;
            $totalTva     = 0.0;
            $useReduction = (bool) ($data['use_reduction'] ?? false);
            $paymentType  = $useReduction ? 'reduction' : ($data['payment_type'] ?? 'cash');
            $client       = null;
            $clientPhone  = trim((string) ($data['client_phone'] ?? ''));

            if ($useReduction && ($data['payment_type'] ?? null) === 'credit') {
                throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
            }
            if ($useReduction && $clientPhone === '') {
                throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour utiliser la réduction.']);
            }
            if ($paymentType === 'credit' && $clientPhone === '') {
                throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour une vente à crédit.']);
            }
            if ($clientPhone !== '') {
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (! $client) {
                    throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
                }
            }

            $numero = Facture::genererNumero($entrepriseId);

            $facturePayload = [
                'entreprise_id' => $entrepriseId,
                'numero'        => $numero,
                'tva'           => $tva,
                'total_ht'      => 0,
                'total_tva'     => 0,
                'total_ttc'     => 0,
                'total_montant' => 0,
                'prix_hors_tva' => 0,
                'montant_paye'  => 0,
                'statut'        => $paymentType === 'credit' ? 'en_attente' : 'payee',
                'date_facture'  => now(),
            ];

            if (Schema::hasColumn('factures', 'succursale_id'))               $facturePayload['succursale_id']    = $succursaleId;
            if ($client && Schema::hasColumn('factures', 'client_id'))        $facturePayload['client_id']        = $client->id;
            if ($client && Schema::hasColumn('factures', 'client_nom'))       $facturePayload['client_nom']       = $client->nom_client;
            if ($client && Schema::hasColumn('factures', 'client_telephone')) $facturePayload['client_telephone'] = $client->numero_telephone;
            if (! Schema::hasColumn('factures', 'user_id'))  unset($facturePayload['user_id']);
            else                                              $facturePayload['user_id'] = $userId;
            if (! Schema::hasColumn('factures', 'total_montant')) unset($facturePayload['total_montant']);
            if (! Schema::hasColumn('factures', 'prix_hors_tva')) unset($facturePayload['prix_hors_tva']);
            if (! Schema::hasColumn('factures', 'montant_paye'))  unset($facturePayload['montant_paye']);
            if (! Schema::hasColumn('factures', 'date_facture'))  unset($facturePayload['date_facture']);

            $facture = Facture::create($facturePayload);

            foreach ($data['lignes'] as $ligne) {
                $produit       = Produit::where('entreprise_id', $entrepriseId)->findOrFail($ligne['produit_id']);
                $prixTtc       = $ligne['prix_unitaire'] ?? $produit->prix_vente;
                $quantite      = (int) $ligne['quantite'];
                $ligneTotalTtc = $quantite * $prixTtc;
                $ligneTotalHt  = $tva > 0 ? $ligneTotalTtc / (1 + ($tva / 100)) : $ligneTotalTtc;
                $ligneTva      = $ligneTotalTtc - $ligneTotalHt;

                $lignePayload = [
                    'facture_id'  => $facture->id,
                    'produit_id'  => $produit->id,
                    'designation' => $produit->nom ?? $produit->designation ?? 'Produit',
                    'quantite'    => $quantite,
                    'prix_ttc'    => $prixTtc,
                    'total'       => $ligneTotalTtc,
                ];
                if (Schema::hasColumn('facture_lignes', 'succursale_id')) {
                    $lignePayload['succursale_id'] = $succursaleId;
                }
                FactureLigne::create($lignePayload);

                $this->service->record($entrepriseId, $produit, 'sortie', $quantite, $prixTtc, 'Vente - ' . ($produit->nom ?? 'Produit'), $userId, $paymentType, $succursaleId);

                $totalTtc += $ligneTotalTtc;
                $totalHt  += $ligneTotalHt;
                $totalTva += $ligneTva;
            }

            $totauxPayload = ['total_ht' => $totalHt, 'total_tva' => $totalTva, 'total_ttc' => $totalTtc, 'total_montant' => $totalTtc, 'prix_hors_tva' => $totalHt];
            if (! Schema::hasColumn('factures', 'total_montant')) unset($totauxPayload['total_montant']);
            if (! Schema::hasColumn('factures', 'prix_hors_tva')) unset($totauxPayload['prix_hors_tva']);
            $facture->update($totauxPayload);

            if ($client) {
                if ($paymentType === 'credit') {
                    $client->creance = (float) $client->creance + $totalTtc;
                } elseif ($paymentType === 'reduction') {
                    $reductionDispo = (float) $client->reduction_accordee;
                    $reductionUsed  = min($totalTtc, $reductionDispo);
                    $resteAPayer    = $totalTtc - $reductionUsed;
                    $client->reduction_accordee = max($reductionDispo - $reductionUsed, 0);
                    $client->achat_mensuel = 0;
                    $this->recordReductionUsage($entrepriseId, 'client', $client->id, $reductionUsed, (float) $client->reduction_accordee, $succursaleId);
                    if ($resteAPayer > 0) {
                        $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Vente (réduction) : {$client->nom_client}", 'date_operation' => now(), 'entree' => $resteAPayer, 'sortie' => 0];
                        if (Schema::hasColumn('caisses', 'succursale_id')) $caisseData['succursale_id'] = $succursaleId;
                        if (Schema::hasColumn('caisses', 'type_operation')) $caisseData['type_operation'] = 'auto';
                        CaisseService::createOperation($caisseData);
                    }
                } else {
                    $client->achat_mensuel      = (float) $client->achat_mensuel + $totalTtc;
                    $client->reduction_accordee = $tauxReduction > 0
                        ? round($client->achat_mensuel * ($tauxReduction / 100), 2)
                        : 0;
                }
                $client->save();
            }

            return $facture;
        });

        $this->archiverFacture($facture);

        return $facture;
    }

    private function archiverFacture(Facture $facture): void
    {
        $exists = Archive::where('entreprise_id', $facture->entreprise_id)
            ->when(Schema::hasColumn('archives', 'succursale_id') && $facture->succursale_id, fn ($q) => $q->where('succursale_id', $facture->succursale_id))
            ->where('type', 'facture')
            ->whereDate('date_archive', $facture->date_facture ?? now())
            ->where('reference_id', (string) $facture->id)
            ->exists();
        if ($exists) return;

        $facture->loadMissing('lignes', 'client');
        $archiveData = [
            'entreprise_id' => $facture->entreprise_id,
            'type'          => 'facture',
            'date_archive'  => ($facture->date_facture ?? now())->toDateString(),
            'reference_id'  => (string) $facture->id,
            'payload'       => [
                'id' => $facture->id, 'numero' => $facture->numero, 'date_facture' => $facture->date_facture,
                'client' => $facture->client_nom ?? $facture->client?->nom_client ?? null,
                'client_phone' => $facture->client_telephone ?? $facture->client?->numero_telephone ?? null,
                'total_ht' => $facture->total_ht, 'total_tva' => $facture->total_tva,
                'total_ttc' => $facture->total_ttc ?? $facture->total_montant,
                'tva' => $facture->tva, 'statut' => $facture->statut,
                'lignes' => $facture->lignes->map(fn ($l) => ['designation' => $l->designation, 'quantite' => $l->quantite, 'prix_ttc' => $l->prix_ttc, 'total' => $l->total])->toArray(),
            ],
        ];
        if (Schema::hasColumn('archives', 'succursale_id')) {
            $archiveData['succursale_id'] = $facture->succursale_id;
        }
        SuccursaleContext::withoutScope(Archive::class)->create($archiveData);
    }

    private function recordReductionUsage(int $entrepriseId, string $entityType, int $entityId, float $utilise, float $reste, ?int $succursaleId): void
    {
        $payload = ['entreprise_id' => $entrepriseId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'montant_utilise' => $utilise, 'reste_apres' => $reste];
        if (Schema::hasColumn('reduction_usages', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        ReductionUsage::create($payload);
    }
}
```

- [ ] **Step 5 : Remplacer genererFactureVente() dans le controller**

Dans `MouvementStockController`, remplacer `genererFactureVente()` et supprimer `archiverFacture()` :

```php
// Imports à ajouter :
use App\Actions\MouvementStock\GenererFactureVenteAction;
use App\Http\Requests\MouvementStock\GenererFactureVenteRequest;

// Remplacer genererFactureVente() par :
public function genererFactureVente(GenererFactureVenteRequest $request): RedirectResponse
{
    $facture = (new GenererFactureVenteAction(app(MouvementStockService::class)))->execute(
        $request->validated(),
        $request->user()->entreprise_id,
        session('succursale_id'),
        $request->user()->id,
    );

    return redirect()->route('factures.show', $facture->id);
}
```

- [ ] **Step 6 : Relancer le test**

```bash
php artisan test --filter GenererFactureVenteActionTest
```

Expected: PASS.

- [ ] **Step 7 : Commit**

```bash
git add app/Http/Requests/MouvementStock/GenererFactureVenteRequest.php \
        app/Actions/MouvementStock/GenererFactureVenteAction.php \
        app/Http/Controllers/MouvementStockController.php \
        tests/Feature/Actions/GenererFactureVenteActionTest.php
git commit -m "refactor(mouvement-stock): extraire genererFactureVente() dans GenererFactureVenteAction"
```

---

### Task 3 : CreerBonEntreeRequest + CreerBonEntreeAction

**Files:**
- Create: `app/Http/Requests/MouvementStock/CreerBonEntreeRequest.php`
- Create: `app/Actions/MouvementStock/CreerBonEntreeAction.php`
- Modify: `app/Http/Controllers/MouvementStockController.php` (méthode `genererBonEntree()` + supprimer `archiverBonEntree`)
- Test: `tests/Feature/Actions/CreerBonEntreeActionTest.php`

---

- [ ] **Step 1 : Écrire le test Feature**

Créer `tests/Feature/Actions/CreerBonEntreeActionTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Models\BonEntree;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreerBonEntreeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bon_entree_incremente_stock(): void
    {
        $entreprise  = Entreprise::create(['name' => 'Test', 'plan' => 'premium']);
        $user        = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => bcrypt('p'), 'role' => 'super_admin', 'entreprise_id' => $entreprise->id]);
        $fournisseur = Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom_entreprise_fournisseur' => 'Pharma', 'reduction_pourcentage' => 0, 'reduction_obtenue' => 0, 'achat_mensuel' => 0, 'dette' => 0]);
        $produit     = Produit::create(['entreprise_id' => $entreprise->id, 'nom' => 'Ibuprofène', 'prix_achat' => 300, 'prix_vente' => 600]);

        DB::table('stocks')->insert([
            'entreprise_id' => $entreprise->id, 'produit_id' => $produit->id,
            'quantite' => 10, 'prix_achat' => 300, 'prix_vente' => 600,
            'total_achat' => 3000, 'total_vente' => 6000, 'seuil_stock' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/mouvement-stocks/generer-bon-entree', [
                'fournisseur_id' => $fournisseur->id,
                'payment_type'   => 'cash',
                'lignes'         => [
                    ['produit_id' => $produit->id, 'quantite' => 50, 'prix_unitaire' => 300],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bon_entrees', ['entreprise_id' => $entreprise->id]);
        $this->assertEquals(60, DB::table('stocks')->where('produit_id', $produit->id)->value('quantite'));
    }
}
```

- [ ] **Step 2 : Lancer le test**

```bash
php artisan test --filter CreerBonEntreeActionTest
```

Expected: PASS (comportement existant).

- [ ] **Step 3 : Créer CreerBonEntreeRequest**

Créer `app/Http/Requests/MouvementStock/CreerBonEntreeRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\MouvementStock;

use Illuminate\Foundation\Http\FormRequest;

class CreerBonEntreeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fournisseur_id'         => ['required', 'exists:fournisseurs,id'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.produit_id'    => ['required', 'exists:produits,id'],
            'lignes.*.quantite'      => ['required', 'integer', 'min:1'],
            'lignes.*.prix_unitaire' => ['nullable', 'numeric', 'min:0'],
            'payment_type'           => ['nullable', 'in:cash,credit,reduction'],
            'use_reduction'          => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4 : Créer CreerBonEntreeAction**

Créer `app/Actions/MouvementStock/CreerBonEntreeAction.php` en déplaçant TOUTE la logique de `genererBonEntree()` + `archiverBonEntree()` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\MouvementStock;

use App\Models\Archive;
use App\Models\BonEntree;
use App\Models\BonEntreeLigne;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\ReductionUsage;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use App\Support\SuccursaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreerBonEntreeAction
{
    public function __construct(private readonly MouvementStockService $service) {}

    public function execute(array $data, int $entrepriseId, ?int $succursaleId): BonEntree
    {
        $hasFournisseurs = Schema::hasColumn('fournisseurs', 'succursale_id');
        $useReduction    = (bool) ($data['use_reduction'] ?? false);
        $paymentType     = $useReduction ? 'reduction' : ($data['payment_type'] ?? 'cash');

        if ($useReduction && ($data['payment_type'] ?? null) === 'credit') {
            throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
        }

        $bon = DB::transaction(function () use ($data, $entrepriseId, $paymentType, $useReduction, $succursaleId, $hasFournisseurs): BonEntree {
            $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $hasFournisseurs, fn ($q) => $q->where('succursale_id', $succursaleId))
                ->findOrFail($data['fournisseur_id']);

            $bonPayload = ['entreprise_id' => $entrepriseId, 'fournisseur_id' => $fournisseur->id, 'total_montant' => 0, 'date_bon' => now(), 'payment_type' => $paymentType];
            if (Schema::hasColumn('bon_entrees', 'succursale_id')) {
                $bonPayload['succursale_id'] = $succursaleId;
            }
            $bon   = BonEntree::create($bonPayload);
            $total = 0.0;

            foreach ($data['lignes'] as $ligne) {
                $produit      = Produit::where('entreprise_id', $entrepriseId)->findOrFail($ligne['produit_id']);
                $quantite     = (int) $ligne['quantite'];
                $prixUnitaire = (float) ($ligne['prix_unitaire'] ?? $produit->prix_achat);
                $ligneTotal   = $quantite * $prixUnitaire;

                $lignePayload = ['bon_entree_id' => $bon->id, 'produit_id' => $produit->id, 'quantite' => $quantite, 'prix_unitaire' => $prixUnitaire, 'total' => $ligneTotal];
                if (Schema::hasColumn('bon_entree_lignes', 'succursale_id')) {
                    $lignePayload['succursale_id'] = $succursaleId;
                }
                BonEntreeLigne::create($lignePayload);

                $this->service->record($entrepriseId, $produit, 'entree', $quantite, $prixUnitaire, "Bon d'entrée " . $bon->numero, null, $paymentType, $succursaleId);
                $total += $ligneTotal;
            }

            $bon->update(['total_montant' => $total]);

            if ($paymentType === 'credit') {
                $fournisseur->dette = (float) $fournisseur->dette + $total;
            } elseif ($paymentType === 'reduction') {
                $reductionDispo = (float) $fournisseur->reduction_obtenue;
                $reductionUsed  = min($total, $reductionDispo);
                $resteAPayer    = $total - $reductionUsed;
                $fournisseur->reduction_obtenue = max($reductionDispo - $reductionUsed, 0);
                $fournisseur->achat_mensuel     = 0;
                $this->recordReductionUsage($entrepriseId, 'fournisseur', $fournisseur->id, $reductionUsed, (float) $fournisseur->reduction_obtenue, $succursaleId);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Achat (réduction) : {$fournisseur->nom_entreprise_fournisseur}", 'date_operation' => now(), 'entree' => 0, 'sortie' => $resteAPayer];
                    if (Schema::hasColumn('caisses', 'succursale_id'))   $caisseData['succursale_id']   = $succursaleId;
                    if (Schema::hasColumn('caisses', 'type_operation'))  $caisseData['type_operation']  = 'auto';
                    CaisseService::createOperation($caisseData);
                }
            } else {
                $fournisseur->achat_mensuel     = (float) $fournisseur->achat_mensuel + $total;
                $taux                           = (float) $fournisseur->reduction_pourcentage;
                $fournisseur->reduction_obtenue = $taux > 0 ? round($fournisseur->achat_mensuel * ($taux / 100), 2) : 0;
            }
            $fournisseur->save();

            return $bon;
        });

        $this->archiverBonEntree($bon);

        return $bon;
    }

    private function archiverBonEntree(BonEntree $bon): void
    {
        $exists = Archive::where('entreprise_id', $bon->entreprise_id)
            ->when(Schema::hasColumn('archives', 'succursale_id') && $bon->succursale_id, fn ($q) => $q->where('succursale_id', $bon->succursale_id))
            ->where('type', 'bon_entree')
            ->whereDate('date_archive', $bon->date_bon ?? now())
            ->where('reference_id', (string) $bon->id)
            ->exists();
        if ($exists) return;

        $bon->loadMissing('lignes', 'lignes.produit', 'fournisseur');
        $archiveData = [
            'entreprise_id' => $bon->entreprise_id,
            'type'          => 'bon_entree',
            'date_archive'  => ($bon->date_bon ?? now())->toDateString(),
            'reference_id'  => (string) $bon->id,
            'payload'       => [
                'id' => $bon->id, 'numero' => $bon->numero, 'date_bon' => $bon->date_bon,
                'fournisseur' => $bon->fournisseur?->nom_entreprise_fournisseur,
                'total'       => $bon->total_montant,
                'lignes'      => $bon->lignes->map(fn ($l) => ['designation' => $l->produit?->nom ?? 'Produit', 'quantite' => $l->quantite, 'prix_unitaire' => $l->prix_unitaire, 'total' => $l->total])->toArray(),
            ],
        ];
        if (Schema::hasColumn('archives', 'succursale_id')) {
            $archiveData['succursale_id'] = $bon->succursale_id;
        }
        SuccursaleContext::withoutScope(Archive::class)->create($archiveData);
    }

    private function recordReductionUsage(int $entrepriseId, string $entityType, int $entityId, float $utilise, float $reste, ?int $succursaleId): void
    {
        $payload = ['entreprise_id' => $entrepriseId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'montant_utilise' => $utilise, 'reste_apres' => $reste];
        if (Schema::hasColumn('reduction_usages', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        ReductionUsage::create($payload);
    }
}
```

- [ ] **Step 5 : Remplacer genererBonEntree() dans le controller**

Dans `MouvementStockController`, remplacer `genererBonEntree()` et supprimer `archiverBonEntree()`. À ce stade, le controller ne doit plus avoir aucune méthode privée :

```php
// Imports à ajouter :
use App\Actions\MouvementStock\CreerBonEntreeAction;
use App\Http\Requests\MouvementStock\CreerBonEntreeRequest;

// Remplacer genererBonEntree() par :
public function genererBonEntree(CreerBonEntreeRequest $request): RedirectResponse
{
    $bon = (new CreerBonEntreeAction(app(MouvementStockService::class)))->execute(
        $request->validated(),
        $request->user()->entreprise_id,
        session('succursale_id'),
    );

    return redirect()->route('mouvement-stocks.index')->with('success', "Bon d'entrée {$bon->numero} enregistré.");
}
```

À la fin de cette tâche, vérifier que `MouvementStockController` ne contient AUCUNE méthode privée et que `index()`, `store()`, `genererFactureVente()`, `genererBonEntree()` font chacun ≤ 10 lignes.

- [ ] **Step 6 : Lancer TOUS les tests MouvementStock**

```bash
php artisan test --filter "CreerMouvementActionTest|GenererFactureVenteActionTest|CreerBonEntreeActionTest"
```

Expected: `4 tests` — tous PASS.

- [ ] **Step 7 : Commit**

```bash
git add app/Http/Requests/MouvementStock/CreerBonEntreeRequest.php \
        app/Actions/MouvementStock/CreerBonEntreeAction.php \
        app/Http/Controllers/MouvementStockController.php \
        tests/Feature/Actions/CreerBonEntreeActionTest.php
git commit -m "refactor(mouvement-stock): extraire genererBonEntree() dans CreerBonEntreeAction — controller réduit à 0 méthode privée"
```

---

## PHASE 2 — Transferts

---

### Task 4 : CreerTransfertCaisseAction + StoreStockTransfertAction

**Files:**
- Create: `app/Http/Requests/Transferts/StoreCaisseTransfertRequest.php`
- Create: `app/Http/Requests/Transferts/StoreStockTransfertRequest.php`
- Create: `app/Actions/Transferts/CreerTransfertCaisseAction.php`
- Create: `app/Actions/Transferts/CreerTransfertStockAction.php`
- Modify: `app/Http/Controllers/TransfertController.php` (`storeCaisse()` + `storeStock()`)
- Test: `tests/Feature/Actions/TransfertActionTest.php`

---

- [ ] **Step 1 : Écrire le test Feature**

Créer `tests/Feature/Actions/TransfertActionTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Models\Entreprise;
use App\Models\Produit;
use App\Models\Succursale;
use App\Models\Transfert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransfertActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Succursale $sucA;
    private Succursale $sucB;
    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $entreprise = Entreprise::create(['name' => 'TestCorp', 'plan' => 'pro']);
        $this->user = User::create([
            'name' => 'Boss', 'email' => 'boss@test.com',
            'password' => Hash::make('secret'), 'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $this->sucA = Succursale::create(['entreprise_id' => $entreprise->id, 'nom' => 'Alpha']);
        $this->sucB = Succursale::create(['entreprise_id' => $entreprise->id, 'nom' => 'Beta']);

        $this->produit = Produit::create([
            'entreprise_id' => $entreprise->id, 'nom' => 'Médicament', 'prix_achat' => 100, 'prix_vente' => 200,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $entreprise->id, 'succursale_id' => $this->sucA->id, 'produit_id' => $this->produit->id,
            'quantite' => 50, 'prix_achat' => 100, 'prix_vente' => 200,
            'total_achat' => 5000, 'total_vente' => 10000, 'seuil_stock' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_demande_transfert_caisse_cree_transfert_pending(): void
    {
        $this->actingAs($this->user)
            ->post('/transferts/caisse', [
                'to_succursale_id' => $this->sucB->id,
                'montant'          => 500,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transferts', [
            'entreprise_id'    => $this->user->entreprise_id,
            'type'             => 'caisse',
            'status'           => 'pending',
            'montant'          => 500,
        ]);
    }

    public function test_demande_transfert_stock_cree_transfert_pending(): void
    {
        $this->actingAs($this->user)
            ->post('/transferts/stock', [
                'to_succursale_id' => $this->sucB->id,
                'produit_id'       => $this->produit->id,
                'quantite'         => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transferts', [
            'entreprise_id' => $this->user->entreprise_id,
            'type'          => 'stock',
            'status'        => 'pending',
        ]);
    }
}
```

- [ ] **Step 2 : Lancer le test**

```bash
php artisan test --filter TransfertActionTest::test_demande_transfert_caisse_cree_transfert_pending
php artisan test --filter TransfertActionTest::test_demande_transfert_stock_cree_transfert_pending
```

Expected: PASS.

- [ ] **Step 3 : Créer StoreCaisseTransfertRequest et StoreStockTransfertRequest**

Créer `app/Http/Requests/Transferts/StoreCaisseTransfertRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Transferts;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaisseTransfertRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to_succursale_id' => ['required', 'integer'],
            'montant'          => ['required', 'numeric', 'min:0.01'],
            'date_operation'   => ['nullable', 'date'],
        ];
    }
}
```

Créer `app/Http/Requests/Transferts/StoreStockTransfertRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Transferts;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransfertRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to_succursale_id' => ['required', 'integer'],
            'produit_id'       => ['required', 'integer'],
            'quantite'         => ['required', 'numeric', 'min:0.01'],
            'date_operation'   => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4 : Créer CreerTransfertCaisseAction et CreerTransfertStockAction**

Créer `app/Actions/Transferts/CreerTransfertCaisseAction.php` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\Transferts;

use App\Models\Succursale;
use App\Models\Transfert;
use Illuminate\Support\Facades\Auth;

class CreerTransfertCaisseAction
{
    public function execute(array $data, int $entrepriseId, ?int $fromSuccursaleId): Transfert
    {
        $toSuccursaleId = Succursale::where('entreprise_id', $entrepriseId)
            ->whereKey((int) $data['to_succursale_id'])
            ->value('id');

        if (! $toSuccursaleId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_succursale_id' => 'Succursale de destination invalide.']);
        }

        if ($fromSuccursaleId && (int) $data['to_succursale_id'] === $fromSuccursaleId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_succursale_id' => 'La succursale de destination doit être différente.']);
        }

        $montant        = (float) $data['montant'];
        $dateOperation  = $data['date_operation'] ?? now();
        $fromSuccursale = $fromSuccursaleId ? Succursale::find($fromSuccursaleId) : null;
        $toSuccursale   = Succursale::find($toSuccursaleId);

        return Transfert::create([
            'entreprise_id'      => $entrepriseId,
            'from_succursale_id' => $fromSuccursaleId,
            'to_succursale_id'   => $toSuccursaleId,
            'user_id'            => Auth::id(),
            'type'               => 'caisse',
            'montant'            => $montant,
            'status'             => 'pending',
            'date_operation'     => $dateOperation,
            'payload'            => [
                'type'            => 'caisse',
                'from_succursale' => $fromSuccursale?->nom ?? 'Central',
                'to_succursale'   => $toSuccursale?->nom,
                'montant'         => $montant,
                'date_operation'  => $dateOperation,
                'user_id'         => Auth::id(),
            ],
        ]);
    }
}
```

Créer `app/Actions/Transferts/CreerTransfertStockAction.php` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\Transferts;

use App\Models\Produit;
use App\Models\Succursale;
use App\Models\Transfert;
use Illuminate\Support\Facades\Auth;

class CreerTransfertStockAction
{
    public function execute(array $data, int $entrepriseId, ?int $fromSuccursaleId): Transfert
    {
        $toSuccursaleId = Succursale::where('entreprise_id', $entrepriseId)
            ->whereKey((int) $data['to_succursale_id'])
            ->value('id');

        if (! $toSuccursaleId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_succursale_id' => 'Succursale de destination invalide.']);
        }

        if ($fromSuccursaleId && (int) $data['to_succursale_id'] === $fromSuccursaleId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_succursale_id' => 'La succursale de destination doit être différente.']);
        }

        $produit        = Produit::where('entreprise_id', $entrepriseId)->findOrFail($data['produit_id']);
        $quantite       = (float) $data['quantite'];
        $dateOperation  = $data['date_operation'] ?? now();
        $fromSuccursale = $fromSuccursaleId ? Succursale::find($fromSuccursaleId) : null;
        $toSuccursale   = Succursale::find($toSuccursaleId);

        return Transfert::create([
            'entreprise_id'      => $entrepriseId,
            'from_succursale_id' => $fromSuccursaleId,
            'to_succursale_id'   => $toSuccursaleId,
            'produit_id'         => $produit->id,
            'user_id'            => Auth::id(),
            'type'               => 'stock',
            'quantite'           => $quantite,
            'status'             => 'pending',
            'date_operation'     => $dateOperation,
            'payload'            => [
                'type'            => 'stock',
                'from_succursale' => $fromSuccursale?->nom ?? 'Central',
                'to_succursale'   => $toSuccursale?->nom,
                'produit_id'      => $produit->id,
                'produit'         => $produit->nom,
                'quantite'        => $quantite,
                'date_operation'  => $dateOperation,
                'user_id'         => Auth::id(),
            ],
        ]);
    }
}
```

- [ ] **Step 5 : Remplacer storeCaisse() et storeStock() dans le controller**

Dans `TransfertController`, remplacer les deux méthodes :

```php
// Imports à ajouter :
use App\Actions\Transferts\CreerTransfertCaisseAction;
use App\Actions\Transferts\CreerTransfertStockAction;
use App\Http\Requests\Transferts\StoreCaisseTransfertRequest;
use App\Http\Requests\Transferts\StoreStockTransfertRequest;
use App\Support\SuccursaleContext;

// Remplacer storeCaisse() :
public function storeCaisse(StoreCaisseTransfertRequest $request): RedirectResponse
{
    $entrepriseId = Auth::user()->entreprise_id;
    if (! Succursale::where('entreprise_id', $entrepriseId)->exists()) {
        return back()->withErrors(['succursale' => 'Aucune succursale disponible pour le transfert.']);
    }

    (new CreerTransfertCaisseAction)->execute(
        $request->validated(),
        $entrepriseId,
        SuccursaleContext::currentId(),
    );

    return redirect()->route('transferts.index')->with('success', 'Demande de transfert de caisse enregistrée.');
}

// Remplacer storeStock() :
public function storeStock(StoreStockTransfertRequest $request): RedirectResponse
{
    $entrepriseId = Auth::user()->entreprise_id;
    if (! Succursale::where('entreprise_id', $entrepriseId)->exists()) {
        return back()->withErrors(['succursale' => 'Aucune succursale disponible pour le transfert.']);
    }

    (new CreerTransfertStockAction)->execute(
        $request->validated(),
        $entrepriseId,
        SuccursaleContext::currentId(),
    );

    return redirect()->route('transferts.index')->with('success', 'Demande de transfert de stock enregistrée.');
}
```

- [ ] **Step 6 : Lancer les tests**

```bash
php artisan test --filter TransfertActionTest
```

Expected: `2 tests` — PASS.

- [ ] **Step 7 : Commit**

```bash
git add app/Http/Requests/Transferts/ \
        app/Actions/Transferts/CreerTransfertCaisseAction.php \
        app/Actions/Transferts/CreerTransfertStockAction.php \
        app/Http/Controllers/TransfertController.php \
        tests/Feature/Actions/TransfertActionTest.php
git commit -m "refactor(transferts): extraire storeCaisse/storeStock dans Actions + FormRequests"
```

---

### Task 5 : ApprouverTransfertAction

**Files:**
- Create: `app/Http/Requests/Transferts/ApprouverTransfertRequest.php`
- Create: `app/Actions/Transferts/ApprouverTransfertAction.php`
- Modify: `app/Http/Controllers/TransfertController.php` (`approve()` + `reject()` + supprimer `assertTransferPermission`, `executeCaisseTransfer`, `executeStockTransfer`)

---

- [ ] **Step 1 : Créer ApprouverTransfertRequest**

Créer `app/Http/Requests/Transferts/ApprouverTransfertRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Transferts;

use Illuminate\Foundation\Http\FormRequest;

class ApprouverTransfertRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'admin_password' => ['required', 'string'],
            'reason'         => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2 : Créer ApprouverTransfertAction**

Créer `app/Actions/Transferts/ApprouverTransfertAction.php` en déplaçant les méthodes `assertTransferPermission()`, `executeCaisseTransfer()`, `executeStockTransfer()` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\Transferts;

use App\Models\Archive;
use App\Models\Journal;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Succursale;
use App\Models\Transfert;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use App\Support\SuccursaleContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ApprouverTransfertAction
{
    public function approuver(Transfert $transfert, string $password, int $userId): void
    {
        $this->assertPermission($transfert->from_succursale_id, $password);

        DB::transaction(function () use ($transfert, $userId): void {
            if ($transfert->type === 'caisse') {
                $this->executeCaisse($transfert);
            } else {
                $this->executeStock($transfert);
            }
            $transfert->status      = 'validated';
            $transfert->approved_by = $userId;
            $transfert->approved_at = now();
            $transfert->save();
        });
    }

    public function rejeter(Transfert $transfert, string $password, int $userId, ?string $reason): void
    {
        $this->assertPermission($transfert->from_succursale_id, $password);

        $payload = $transfert->payload ?? [];
        if ($reason) {
            $payload['rejection_reason'] = $reason;
        }
        $transfert->status      = 'rejected';
        $transfert->approved_by = $userId;
        $transfert->approved_at = now();
        $transfert->payload     = $payload;
        $transfert->save();
    }

    private function assertPermission(?int $fromSuccursaleId, string $password): void
    {
        $user       = Auth::user();
        $succursale = null;

        if ($fromSuccursaleId !== null) {
            $succursale = Succursale::where('entreprise_id', $user->entreprise_id)
                ->where('id', $fromSuccursaleId)->first();
        }

        $allowed = $user->isSuperAdmin()
            || ($succursale && (int) $succursale->manager_user_id === (int) $user->id);

        if (! $allowed) {
            abort(403, 'Accès réservé au Super Admin ou au manager de la succursale.');
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['admin_password' => 'Mot de passe Super Admin/Manager incorrect.']);
        }
    }

    private function executeCaisse(Transfert $transfert): void
    {
        $entrepriseId     = $transfert->entreprise_id;
        $fromSuccursaleId = $transfert->from_succursale_id;
        $toSuccursaleId   = $transfert->to_succursale_id;
        $montant          = (float) $transfert->montant;
        $dateOperation    = $transfert->date_operation ?? now();
        $fromLabel        = Succursale::find($fromSuccursaleId)?->nom ?? 'Central';
        $toLabel          = Succursale::find($toSuccursaleId)?->nom   ?? 'Central';

        $caisseOut = CaisseService::createOperation([
            'entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId,
            'description' => "[{$fromLabel}] Transfert de fonds vers {$toLabel}",
            'date_operation' => $dateOperation, 'entree' => 0, 'sortie' => $montant, 'type_operation' => 'transfert_out',
        ]);
        $caisseIn = CaisseService::createOperation([
            'entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId,
            'description' => "[{$toLabel}] Transfert de fonds reçu de {$fromLabel}",
            'date_operation' => $dateOperation, 'entree' => $montant, 'sortie' => 0, 'type_operation' => 'transfert_in',
        ]);

        SuccursaleContext::withoutScope(Journal::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'dateHeure_operation' => $dateOperation, 'type' => MouvementStockService::normalizeType('sortie'), 'description' => "[{$fromLabel}] Transfert caisse envoyé vers {$toLabel}", 'montant' => $montant, 'user_id' => Auth::id()]);
        SuccursaleContext::withoutScope(Journal::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId, 'dateHeure_operation' => $dateOperation, 'type' => MouvementStockService::normalizeType('entree'), 'description' => "[{$toLabel}] Transfert caisse reçu de {$fromLabel}", 'montant' => $montant, 'user_id' => Auth::id()]);

        $payload = ['type' => 'caisse', 'from_succursale' => $fromLabel, 'to_succursale' => $toLabel, 'montant' => $montant, 'date_operation' => $dateOperation, 'user_id' => Auth::id(), 'status' => 'validated'];
        $dateArchive = $dateOperation instanceof \DateTimeInterface ? $dateOperation->format('Y-m-d') : now()->toDateString();

        if (Schema::hasColumn('archives', 'succursale_id')) {
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $caisseOut->id, 'payload' => $payload]);
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId,   'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $caisseIn->id,  'payload' => $payload]);
        } else {
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $caisseOut->id, 'payload' => $payload]);
        }

        $meta = $transfert->payload ?? [];
        $meta['caisse_out_id'] = $caisseOut->id;
        $meta['caisse_in_id']  = $caisseIn->id;
        $transfert->payload    = $meta;
        $transfert->save();
    }

    private function executeStock(Transfert $transfert): void
    {
        $entrepriseId     = $transfert->entreprise_id;
        $fromSuccursaleId = $transfert->from_succursale_id;
        $toSuccursaleId   = $transfert->to_succursale_id;
        $quantite         = (float) $transfert->quantite;
        $dateOperation    = $transfert->date_operation ?? now();

        $produit      = Produit::where('entreprise_id', $entrepriseId)->findOrFail($transfert->produit_id);
        $prixUnitaire = (float) ($produit->prix_achat ?? $produit->prix_vente ?? 0);

        $stockFrom = SuccursaleContext::withoutScope(Stock::class)->firstOrCreate(
            ['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'produit_id' => $produit->id],
            ['quantite' => 0, 'prix_achat' => $produit->prix_achat, 'prix_vente' => $produit->prix_vente, 'total_achat' => 0, 'total_vente' => 0]
        );

        if ((float) $stockFrom->quantite < $quantite) {
            throw ValidationException::withMessages(['quantite' => 'Stock insuffisant dans la succursale source.']);
        }

        $stockTo = SuccursaleContext::withoutScope(Stock::class)->firstOrCreate(
            ['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId, 'produit_id' => $produit->id],
            ['quantite' => 0, 'prix_achat' => $produit->prix_achat, 'prix_vente' => $produit->prix_vente, 'total_achat' => 0, 'total_vente' => 0]
        );

        $mouvementOut = SuccursaleContext::withoutScope(MouvementStock::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'produit_id' => $produit->id, 'type' => 'sortie', 'quantite' => $quantite, 'prix_unitaire' => $prixUnitaire, 'prix_total' => $quantite * $prixUnitaire, 'user_id' => Auth::id(), 'commentaire' => 'Transfert stock vers succursale', 'payment_type' => 'transfer']);
        $mouvementIn  = SuccursaleContext::withoutScope(MouvementStock::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId,   'produit_id' => $produit->id, 'type' => 'entree', 'quantite' => $quantite, 'prix_unitaire' => $prixUnitaire, 'prix_total' => $quantite * $prixUnitaire, 'user_id' => Auth::id(), 'commentaire' => 'Transfert stock depuis succursale', 'payment_type' => 'transfer']);

        $stockFrom->quantite    = (float) $stockFrom->quantite - $quantite;
        $stockFrom->total_achat = (float) $stockFrom->quantite * (float) ($stockFrom->prix_achat ?? $prixUnitaire);
        $stockFrom->total_vente = (float) $stockFrom->quantite * (float) ($stockFrom->prix_vente ?? $produit->prix_vente ?? 0);
        $stockFrom->save();

        $stockTo->quantite    = (float) $stockTo->quantite + $quantite;
        $stockTo->total_achat = (float) $stockTo->quantite * (float) ($stockTo->prix_achat ?? $prixUnitaire);
        $stockTo->total_vente = (float) $stockTo->quantite * (float) ($stockTo->prix_vente ?? $produit->prix_vente ?? 0);
        $stockTo->save();

        $fromLabel   = Succursale::find($fromSuccursaleId)?->nom ?? 'Central';
        $toLabel     = Succursale::find($toSuccursaleId)?->nom   ?? 'Central';
        $dateArchive = $dateOperation instanceof \DateTimeInterface ? $dateOperation->format('Y-m-d') : now()->toDateString();

        SuccursaleContext::withoutScope(Journal::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'produit_id' => $produit->id, 'dateHeure_operation' => $dateOperation, 'type' => 'sortie', 'description' => "[{$fromLabel}] Transfert stock envoyé vers {$toLabel} - {$produit->nom}", 'montant' => 0, 'user_id' => Auth::id()]);
        SuccursaleContext::withoutScope(Journal::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId,   'produit_id' => $produit->id, 'dateHeure_operation' => $dateOperation, 'type' => 'entree', 'description' => "[{$toLabel}] Transfert stock reçu de {$fromLabel} - {$produit->nom}", 'montant' => 0, 'user_id' => Auth::id()]);

        $payload = ['type' => 'stock', 'from_succursale' => $fromLabel, 'to_succursale' => $toLabel, 'produit' => $produit->nom, 'quantite' => $quantite, 'date_operation' => $dateOperation, 'user_id' => Auth::id(), 'status' => 'validated'];

        if (Schema::hasColumn('archives', 'succursale_id')) {
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $mouvementOut->id, 'payload' => $payload]);
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId,   'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $mouvementIn->id,  'payload' => $payload]);
        } else {
            SuccursaleContext::withoutScope(Archive::class)->create(['entreprise_id' => $entrepriseId, 'type' => 'transfert', 'date_archive' => $dateArchive, 'reference_id' => (string) $mouvementOut->id, 'payload' => $payload]);
        }

        $meta = $transfert->payload ?? [];
        $meta['mouvement_out_id'] = $mouvementOut->id;
        $meta['mouvement_in_id']  = $mouvementIn->id;
        $transfert->payload       = $meta;
        $transfert->save();
    }
}
```

- [ ] **Step 3 : Remplacer approve() et reject() dans le controller, supprimer les 3 méthodes privées**

Dans `TransfertController`, remplacer `approve()`, `reject()` et supprimer `assertTransferPermission()`, `executeCaisseTransfer()`, `executeStockTransfer()` :

```php
// Import à ajouter :
use App\Actions\Transferts\ApprouverTransfertAction;
use App\Http\Requests\Transferts\ApprouverTransfertRequest;

// Remplacer approve() :
public function approve(ApprouverTransfertRequest $request, Transfert $transfert): RedirectResponse
{
    if ($transfert->entreprise_id !== Auth::user()->entreprise_id) abort(403);
    if ($transfert->status !== 'pending') {
        return back()->withErrors(['status' => 'Ce transfert est déjà traité.']);
    }

    (new ApprouverTransfertAction)->approuver(
        $transfert,
        $request->validated('admin_password'),
        Auth::id(),
    );

    return redirect()->route('transferts.index')->with('success', 'Transfert validé.');
}

// Remplacer reject() :
public function reject(ApprouverTransfertRequest $request, Transfert $transfert): RedirectResponse
{
    if ($transfert->entreprise_id !== Auth::user()->entreprise_id) abort(403);
    if ($transfert->status !== 'pending') {
        return back()->withErrors(['status' => 'Ce transfert est déjà traité.']);
    }

    (new ApprouverTransfertAction)->rejeter(
        $transfert,
        $request->validated('admin_password'),
        Auth::id(),
        $request->validated('reason'),
    );

    return redirect()->route('transferts.index')->with('success', 'Transfert rejeté.');
}
```

- [ ] **Step 4 : Lancer tous les tests Transferts**

```bash
php artisan test --filter TransfertActionTest
```

Expected: PASS.

- [ ] **Step 5 : Commit**

```bash
git add app/Http/Requests/Transferts/ApprouverTransfertRequest.php \
        app/Actions/Transferts/ApprouverTransfertAction.php \
        app/Http/Controllers/TransfertController.php
git commit -m "refactor(transferts): extraire approve/reject dans ApprouverTransfertAction — controller sans méthode privée"
```

---

## PHASE 3 — Caisse

---

### Task 6 : InitialiserCaisseRequest + InitialiserCaisseAction

**Files:**
- Create: `app/Http/Requests/Caisse/InitialiserCaisseRequest.php`
- Create: `app/Actions/Caisse/InitialiserCaisseAction.php`
- Modify: `app/Http/Controllers/CaisseController.php` (`storeInitial()`)
- Test: `tests/Feature/Actions/InitialiserCaisseActionTest.php`

---

- [ ] **Step 1 : Écrire le test Feature**

Créer `tests/Feature/Actions/InitialiserCaisseActionTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Models\Caisse;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialiserCaisseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialise_la_caisse_une_seule_fois(): void
    {
        $entreprise = Entreprise::create(['name' => 'Test', 'plan' => 'free']);
        $user       = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => bcrypt('p'), 'role' => 'super_admin', 'entreprise_id' => $entreprise->id]);

        $response = $this->actingAs($user)
            ->postJson('/caisse/initial', [
                'montant'     => 10000,
                'description' => 'Solde de départ',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('caisses', [
            'entreprise_id'  => $entreprise->id,
            'entree'         => 10000,
            'type_operation' => 'initial',
        ]);

        // Deuxième appel doit échouer
        $this->actingAs($user)
            ->postJson('/caisse/initial', ['montant' => 5000])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2 : Lancer le test**

```bash
php artisan test --filter InitialiserCaisseActionTest
```

Expected: PASS.

- [ ] **Step 3 : Créer InitialiserCaisseRequest**

Créer `app/Http/Requests/Caisse/InitialiserCaisseRequest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Caisse;

use Illuminate\Foundation\Http\FormRequest;

class InitialiserCaisseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'montant'        => ['required', 'numeric', 'min:0'],
            'description'    => ['nullable', 'string', 'max:255'],
            'date_operation' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4 : Créer InitialiserCaisseAction**

Créer `app/Actions/Caisse/InitialiserCaisseAction.php` :

```php
<?php

declare(strict_types=1);

namespace App\Actions\Caisse;

use App\Models\Caisse;
use App\Services\CaisseService;
use Illuminate\Support\Facades\Schema;

class InitialiserCaisseAction
{
    public function execute(array $data, int $entrepriseId, ?int $succursaleId): Caisse
    {
        if (! Schema::hasColumn('caisses', 'type_operation')) {
            throw new \RuntimeException('Migration manquante : colonne type_operation absente de la table caisses.');
        }

        $existe = Caisse::where('entreprise_id', $entrepriseId)
            ->when(Schema::hasColumn('caisses', 'succursale_id') && $succursaleId, fn ($q) => $q->where('succursale_id', $succursaleId))
            ->where('type_operation', 'initial')
            ->exists();

        if ($existe) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'montant' => 'Le solde initial est déjà défini pour cette entreprise.',
            ]);
        }

        $payload = [
            'entreprise_id'  => $entrepriseId,
            'date_operation' => $data['date_operation'] ?? now(),
            'description'    => $data['description'] ?? 'Solde initial (manuel)',
            'entree'         => $data['montant'],
            'sortie'         => 0,
            'type_operation' => 'initial',
        ];
        if (Schema::hasColumn('caisses', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }

        return CaisseService::createOperation($payload);
    }
}
```

- [ ] **Step 5 : Remplacer storeInitial() dans le controller**

Dans `CaisseController`, remplacer `storeInitial()` :

```php
// Imports à ajouter :
use App\Actions\Caisse\InitialiserCaisseAction;
use App\Http\Requests\Caisse\InitialiserCaisseRequest;
use Illuminate\Http\JsonResponse;

// Remplacer storeInitial() :
public function storeInitial(InitialiserCaisseRequest $request): JsonResponse
{
    $succursaleId = session('succursale_id');

    if ($succursaleId) {
        return response()->json(['message' => 'Le solde initial se définit uniquement au niveau central.'], 422);
    }

    try {
        (new InitialiserCaisseAction)->execute(
            $request->validated(),
            Auth::user()->entreprise_id,
            $succursaleId,
        );
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }

    return response()->json(['message' => 'Solde initial enregistré avec succès.']);
}
```

- [ ] **Step 6 : Relancer le test**

```bash
php artisan test --filter InitialiserCaisseActionTest
```

Expected: PASS.

- [ ] **Step 7 : Lancer la suite complète**

```bash
php artisan test
```

Expected: tous les tests en VERT, aucune régression.

- [ ] **Step 8 : Commit**

```bash
git add app/Http/Requests/Caisse/InitialiserCaisseRequest.php \
        app/Actions/Caisse/InitialiserCaisseAction.php \
        app/Http/Controllers/CaisseController.php \
        tests/Feature/Actions/InitialiserCaisseActionTest.php
git commit -m "refactor(caisse): extraire storeInitial() dans InitialiserCaisseAction + FormRequest"
```

---

## PHASE 4 — Global

---

### Task 7 : declare(strict_types=1) sur tous les fichiers PHP

**Files:** Tous les fichiers `.php` dans `app/`

---

- [ ] **Step 1 : Identifier les fichiers sans strict_types**

```bash
grep -rL "declare(strict_types=1)" app/ --include="*.php"
```

Cette commande liste tous les fichiers PHP sans `declare(strict_types=1)`.

- [ ] **Step 2 : Ajouter declare(strict_types=1) à chaque fichier manquant**

Pour chaque fichier listé, ajouter `declare(strict_types=1);` sur la ligne 3 (après `<?php` et avant `namespace`). La structure attendue est :

```php
<?php

declare(strict_types=1);

namespace App\...;
```

Traiter par groupe logique :

```bash
# Controllers
grep -rL "declare(strict_types=1)" app/Http/Controllers/ --include="*.php"

# Models
grep -rL "declare(strict_types=1)" app/Models/ --include="*.php"

# Services
grep -rL "declare(strict_types=1)" app/Services/ --include="*.php"

# Traits, Jobs, Middleware, etc.
grep -rL "declare(strict_types=1)" app/ --include="*.php"
```

- [ ] **Step 3 : Vérifier — zéro fichier sans strict_types**

```bash
grep -rL "declare(strict_types=1)" app/ --include="*.php"
```

Expected: aucune sortie (tous les fichiers ont le declare).

- [ ] **Step 4 : Lancer les tests**

```bash
php artisan test
```

Expected: VERT.

- [ ] **Step 5 : Commit**

```bash
git add app/
git commit -m "refactor(global): declare(strict_types=1) sur tous les fichiers PHP dans app/"
```

---

### Task 8 : ->paginate(50) sur les listes sans limite

**Files:**
- `app/Http/Controllers/TransfertController.php` — `index()` : `->get()` sans limite
- `app/Http/Controllers/MouvementStockController.php` — `index()` : mouvements du jour déjà bornés par date
- `app/Http/Controllers/ClientController.php` — `index()` si `->get()` sans limite
- `app/Http/Controllers/FournisseurController.php` — `index()` si `->get()` sans limite

---

- [ ] **Step 1 : Identifier les listes sans limite**

```bash
grep -rn "->get()" app/Http/Controllers/ --include="*.php" | grep -v "first()\|exists()\|value("
```

- [ ] **Step 2 : Ajouter ->paginate(50) dans TransfertController::index()**

Dans `TransfertController::index()`, remplacer le `->get()` final de la requête `$transferts` par `->paginate(50)`. Remplacer ensuite `->transform(` par `->through(` pour que la transformation fonctionne avec un paginator Inertia :

```php
// Avant (ligne ~34-53 de TransfertController) :
$transferts = Transfert::with([...])
    ->where('entreprise_id', $entrepriseId)
    ->when(...)
    ->orderByDesc('date_operation')
    ->orderByDesc('id')
    ->get();

$transferts->transform(function (Transfert $transfert) { ... });

// Après :
$transferts = Transfert::with([...])
    ->where('entreprise_id', $entrepriseId)
    ->when(...)
    ->orderByDesc('date_operation')
    ->orderByDesc('id')
    ->paginate(50);

$transferts->through(function (Transfert $transfert) { ... });
```

- [ ] **Step 3 : Vérifier ClientController et FournisseurController**

```bash
grep -n "->get()" app/Http/Controllers/ClientController.php app/Http/Controllers/FournisseurController.php 2>/dev/null
```

Si des `->get()` sans `->limit()` sont présents dans `index()`, les remplacer par `->paginate(50)`.

- [ ] **Step 4 : Lancer les tests**

```bash
php artisan test
```

Expected: VERT.

- [ ] **Step 5 : Commit**

```bash
git add app/Http/Controllers/TransfertController.php \
        app/Http/Controllers/ClientController.php \
        app/Http/Controllers/FournisseurController.php
git commit -m "refactor(global): paginate(50) sur les listes non bornées"
```

---

## Vérification finale

Après toutes les tâches, vérifier :

```bash
# Tous les tests passent
php artisan test

# Plus aucune méthode privée dans MouvementStockController, TransfertController
grep -c "private function" app/Http/Controllers/MouvementStockController.php
# Expected: 0

grep -c "private function" app/Http/Controllers/TransfertController.php
# Expected: 0

# Tous les fichiers PHP ont strict_types
grep -rL "declare(strict_types=1)" app/ --include="*.php"
# Expected: aucune sortie

# Longueur des controllers après refactoring
wc -l app/Http/Controllers/MouvementStockController.php
# Expected: < 100 lignes (depuis 579)

wc -l app/Http/Controllers/TransfertController.php
# Expected: < 80 lignes (depuis 537)
```
