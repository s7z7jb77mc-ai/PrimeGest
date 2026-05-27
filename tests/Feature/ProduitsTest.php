<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProduitsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::factory()->create(['plan' => 'premium']);
        $this->user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $this->entreprise->update(['user_id' => $this->user->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_creer_produit(): void
    {
        $this->actingAs($this->user)
            ->post('/produits', [
                'nom'         => 'Aspirine 500mg',
                'prix_achat'  => 500,
                'prix_vente'  => 1000,
                'seuil_stock' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('produits', [
            'nom'           => 'Aspirine 500mg',
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    public function test_produit_store_cree_stock_initial_a_zero(): void
    {
        $this->actingAs($this->user)
            ->post('/produits', [
                'nom'        => 'Paracétamol',
                'prix_achat' => 300,
                'prix_vente' => 700,
            ])
            ->assertRedirect();

        $produit = Produit::withoutGlobalScopes()
            ->where('entreprise_id', $this->entreprise->id)
            ->where('nom', 'Paracétamol')
            ->firstOrFail();

        $this->assertDatabaseHas('stocks', [
            'produit_id'    => $produit->id,
            'entreprise_id' => $this->entreprise->id,
            'quantite'      => 0,
        ]);
    }

    public function test_produit_store_requiert_auth(): void
    {
        $this->post('/produits', ['nom' => 'Test', 'prix_achat' => 100, 'prix_vente' => 200])
            ->assertRedirect('/login');
    }

    public function test_produit_store_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->post('/produits', [])
            ->assertSessionHasErrors(['nom', 'prix_achat', 'prix_vente']);
    }

    public function test_produit_store_rejette_prix_negatif(): void
    {
        $this->actingAs($this->user)
            ->post('/produits', [
                'nom'        => 'Test',
                'prix_achat' => -100,
                'prix_vente' => 200,
            ])
            ->assertSessionHasErrors(['prix_achat']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_produit_avec_bon_mdp(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Avant',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->user)
            ->put("/produits/{$produit->id}", [
                'nom'            => 'Après',
                'prix_achat'     => 150,
                'prix_vente'     => 300,
                'admin_password' => 'password',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('produits', ['id' => $produit->id, 'nom' => 'Après']);
    }

    public function test_modification_produit_echoue_mauvais_mdp(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Original',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->user)
            ->put("/produits/{$produit->id}", [
                'nom'            => 'Hack',
                'prix_achat'     => 1,
                'prix_vente'     => 1,
                'admin_password' => 'wrong_password',
            ])
            ->assertSessionHasErrors(['admin_password']);

        $this->assertDatabaseHas('produits', ['id' => $produit->id, 'nom' => 'Original']);
    }

    public function test_produit_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreProduit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'nom'           => 'Adverse',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->user)
            ->put("/produits/{$autreProduit->id}", [
                'nom'            => 'Hack',
                'prix_achat'     => 1,
                'prix_vente'     => 1,
                'admin_password' => 'password',
            ])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_supprimer_produit_avec_bon_mdp(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'À supprimer',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->user)
            ->delete("/produits/{$produit->id}", ['admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseMissing('produits', ['id' => $produit->id]);
    }

    public function test_suppression_produit_bloquee_mauvais_mdp(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Protégé',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->user)
            ->delete("/produits/{$produit->id}", ['admin_password' => 'wrong'])
            ->assertSessionHasErrors(['admin_password']);

        $this->assertDatabaseHas('produits', ['id' => $produit->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plan Freemium — limite produits
    // ─────────────────────────────────────────────────────────────────────────

    public function test_plan_free_bloque_creation_apres_limite(): void
    {
        $entreprise = Entreprise::factory()->create(['plan' => 'free']);
        $user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $entreprise->update(['user_id' => $user->id]);
        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $limit = config('plans.free.produits', 50);

        // Créer exactement la limite de produits
        for ($i = 0; $i < $limit; $i++) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
                'uuid'          => (string) \Illuminate\Support\Str::uuid(),
                'entreprise_id' => $entreprise->id,
                'nom'           => "Produit {$i}",
                'prix_achat'    => 100,
                'prix_vente'    => 200,
            ]));
        }

        // Le prochain doit être bloqué
        $response = $this->actingAs($user)
            ->post('/produits', [
                'nom'        => 'Produit de trop',
                'prix_achat' => 100,
                'prix_vente' => 200,
            ]);

        // Middleware retourne 200 Inertia ou 403 JSON — pas de redirect success
        $this->assertNotSame(302, $response->status(), 'La création doit être bloquée par le plan free');
    }

    public function test_plan_premium_ne_bloque_pas_creation_produits(): void
    {
        Cache::forget("inertia.entreprise.{$this->entreprise->id}");

        $this->actingAs($this->user)
            ->post('/produits', [
                'nom'        => 'Produit premium',
                'prix_achat' => 100,
                'prix_vente' => 200,
            ])
            ->assertRedirect();
    }
}
