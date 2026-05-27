<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employe;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests de sécurité : isolation multi-tenant et contrôle d'accès par rôle.
 *
 * Règles vérifiées :
 * - Un user d'une entreprise A ne peut jamais modifier/supprimer les données de l'entreprise B.
 * - Les routes 'super_admin-only' rejettent les rôles inférieurs.
 * - Un utilisateur non authentifié est redirigé vers login.
 */
class RoleSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;
    private Entreprise $entreprise;

    private User $regularUser;
    private Entreprise $autreEntreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::factory()->create(['plan' => 'premium']);
        $this->superAdmin = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $this->entreprise->update(['user_id' => $this->superAdmin->id]);

        $this->autreEntreprise = Entreprise::factory()->create(['plan' => 'premium']);
        $this->regularUser = User::factory()->create([
            'role'          => 'user',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Routes super_admin-only : rôle inférieur → 403
    // ─────────────────────────────────────────────────────────────────────────

    public function test_user_ne_peut_pas_modifier_produit(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Protégé',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->regularUser)
            ->put("/produits/{$produit->id}", [
                'nom'            => 'Hack',
                'prix_achat'     => 1,
                'prix_vente'     => 1,
                'admin_password' => 'password',
            ])
            ->assertStatus(403);
    }

    public function test_user_ne_peut_pas_supprimer_produit(): void
    {
        $produit = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Protégé',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->regularUser)
            ->delete("/produits/{$produit->id}", ['admin_password' => 'password'])
            ->assertStatus(403);
    }

    public function test_user_ne_peut_pas_modifier_client(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Protégé',
            'numero_telephone' => '0999700001',
        ]));

        $this->actingAs($this->regularUser)
            ->put("/clients/{$client->id}", [
                'nom_client'       => 'Hack',
                'numero_telephone' => '0999700001',
                'admin_password'   => 'password',
            ])
            ->assertStatus(403);
    }

    public function test_user_ne_peut_pas_supprimer_employe(): void
    {
        $employe = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Employe::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Protégé',
            'email'         => 'prot_' . uniqid() . '@test.com',
            'salaire_base'  => 500,
            'statut'        => 'actif',
        ]));

        $this->actingAs($this->regularUser)
            ->delete("/employes/{$employe->id}", ['admin_password' => 'password'])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Isolation multi-tenant : accès aux données d'une autre entreprise
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_ne_peut_pas_supprimer_client_autre_entreprise(): void
    {
        $clientAutre = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->autreEntreprise->id,
            'nom_client'       => 'Client adverse',
            'numero_telephone' => '0999800001',
        ]));

        $this->actingAs($this->superAdmin)
            ->delete("/clients/{$clientAutre->id}", ['admin_password' => 'password'])
            ->assertStatus(403);

        $this->assertDatabaseHas('clients', ['id' => $clientAutre->id]);
    }

    public function test_super_admin_ne_peut_pas_modifier_produit_autre_entreprise(): void
    {
        $produitAutre = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->autreEntreprise->id,
            'nom'           => 'Produit adverse',
            'prix_achat'    => 100,
            'prix_vente'    => 200,
        ]));

        $this->actingAs($this->superAdmin)
            ->put("/produits/{$produitAutre->id}", [
                'nom'            => 'Hack',
                'prix_achat'     => 1,
                'prix_vente'     => 1,
                'admin_password' => 'password',
            ])
            ->assertStatus(403);
    }

    public function test_super_admin_ne_peut_pas_supprimer_fournisseur_autre_entreprise(): void
    {
        $fourn = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->autreEntreprise->id,
            'nom_entreprise_fournisseur' => 'Adverse SARL',
            'adresse'                    => 'Goma',
        ]));

        $this->actingAs($this->superAdmin)
            ->delete("/fournisseurs/{$fourn->id}", ['admin_password' => 'password'])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Non authentifié → redirect login
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_redirige_login_sur_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_redirige_login_sur_clients(): void
    {
        $this->get('/tiers')->assertRedirect('/login');
    }

    public function test_guest_redirige_login_sur_produits(): void
    {
        $this->post('/produits', ['nom' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_guest_redirige_login_sur_fiches(): void
    {
        $this->get('/fiche-paye')->assertRedirect('/login');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Password requis pour toutes les opérations destructives (double vérif)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_sans_mdp_ne_peut_pas_supprimer_client(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Test no pwd',
            'numero_telephone' => '0999900001',
        ]));

        // Sans admin_password du tout
        $this->actingAs($this->superAdmin)
            ->delete("/clients/{$client->id}", [])
            ->assertSessionHasErrors(['admin_password']);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_super_admin_sans_mdp_ne_peut_pas_modifier_fournisseur(): void
    {
        $fourn = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Sans MDP',
            'adresse'                    => 'Kinshasa',
        ]));

        $this->actingAs($this->superAdmin)
            ->put("/fournisseurs/{$fourn->id}", [
                'nom_entreprise_fournisseur' => 'Modif sans mdp',
                'adresse'                    => 'Goma',
            ])
            ->assertSessionHasErrors(['admin_password']);
    }
}
