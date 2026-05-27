<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests CRUD Clients + Fournisseurs (Tiers).
 * Ces deux entités suivent exactement le même pattern : validation,
 * unicité par entreprise, mot de passe requis pour modification.
 */
class TiersTest extends TestCase
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
    // CLIENT — store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_creer_client(): void
    {
        $this->actingAs($this->user)
            ->post('/clients', [
                'nom_client'       => 'Marie Dupont',
                'numero_telephone' => '0999001001',
                'adresse'          => 'Kinshasa, Gombe',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'nom_client'       => 'Marie Dupont',
            'numero_telephone' => '0999001001',
            'entreprise_id'    => $this->entreprise->id,
        ]);
    }

    public function test_client_store_requiert_auth(): void
    {
        $this->post('/clients', ['nom_client' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_client_store_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->post('/clients', [])
            ->assertSessionHasErrors(['nom_client', 'numero_telephone']);
    }

    public function test_client_telephone_unique_par_entreprise(): void
    {
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Existant',
            'numero_telephone' => '0999002002',
        ]));

        $this->actingAs($this->user)
            ->post('/clients', [
                'nom_client'       => 'Doublon',
                'numero_telephone' => '0999002002',
            ])
            ->assertSessionHasErrors(['numero_telephone']);
    }

    public function test_meme_telephone_accepte_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $autreEntreprise->id,
            'nom_client'       => 'Autre co',
            'numero_telephone' => '0999003003',
        ]));

        $this->actingAs($this->user)
            ->post('/clients', [
                'nom_client'       => 'Notre client',
                'numero_telephone' => '0999003003',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'entreprise_id'    => $this->entreprise->id,
            'numero_telephone' => '0999003003',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLIENT — update
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_client_avec_bon_mdp(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Avant',
            'numero_telephone' => '0999004001',
        ]));

        $this->actingAs($this->user)
            ->put("/clients/{$client->id}", [
                'nom_client'       => 'Après',
                'numero_telephone' => '0999004001',
                'admin_password'   => 'password',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'nom_client' => 'Après']);
    }

    public function test_modification_client_echoue_sans_mdp(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Test',
            'numero_telephone' => '0999004002',
        ]));

        $this->actingAs($this->user)
            ->put("/clients/{$client->id}", [
                'nom_client'       => 'Modif',
                'numero_telephone' => '0999004002',
                'admin_password'   => 'mauvais_mdp',
            ])
            ->assertSessionHasErrors(['admin_password']);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'nom_client' => 'Test']);
    }

    public function test_client_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreClient = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $autreEntreprise->id,
            'nom_client'       => 'Client adverse',
            'numero_telephone' => '0999005001',
        ]));

        $this->actingAs($this->user)
            ->put("/clients/{$autreClient->id}", [
                'nom_client'       => 'Hack',
                'numero_telephone' => '0999005001',
                'admin_password'   => 'password',
            ])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLIENT — destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_supprimer_client_avec_bon_mdp(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'À supprimer',
            'numero_telephone' => '0999006001',
        ]));

        $this->actingAs($this->user)
            ->delete("/clients/{$client->id}", ['admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_suppression_client_bloquee_mauvais_mdp(): void
    {
        $client = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Protégé',
            'numero_telephone' => '0999006002',
        ]));

        $this->actingAs($this->user)
            ->delete("/clients/{$client->id}", ['admin_password' => 'wrong'])
            ->assertSessionHasErrors(['admin_password']);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FOURNISSEUR — store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_creer_fournisseur(): void
    {
        $this->actingAs($this->user)
            ->post('/fournisseurs', [
                'nom_entreprise_fournisseur' => 'Pharma Congo SARL',
                'adresse'                    => 'Goma, Avenue Volcans',
                'reduction_pourcentage'      => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fournisseurs', [
            'nom_entreprise_fournisseur' => 'Pharma Congo SARL',
            'entreprise_id'              => $this->entreprise->id,
        ]);
    }

    public function test_fournisseur_store_requiert_auth(): void
    {
        $this->post('/fournisseurs', ['nom_entreprise_fournisseur' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_fournisseur_store_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->post('/fournisseurs', [])
            ->assertSessionHasErrors(['nom_entreprise_fournisseur']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FOURNISSEUR — update / destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_fournisseur_avec_bon_mdp(): void
    {
        $fournisseur = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Avant',
            'adresse'                    => 'Kinshasa',
        ]));

        $this->actingAs($this->user)
            ->put("/fournisseurs/{$fournisseur->id}", [
                'nom_entreprise_fournisseur' => 'Après',
                'adresse'                    => 'Kinshasa',
                'admin_password'             => 'password',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fournisseurs', ['id' => $fournisseur->id, 'nom_entreprise_fournisseur' => 'Après']);
    }

    public function test_modification_fournisseur_echoue_mauvais_mdp(): void
    {
        $fournisseur = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Original',
            'adresse'                    => 'Goma',
        ]));

        $this->actingAs($this->user)
            ->put("/fournisseurs/{$fournisseur->id}", [
                'nom_entreprise_fournisseur' => 'Hack',
                'adresse'                    => 'Goma',
                'admin_password'             => 'wrong',
            ])
            ->assertSessionHasErrors(['admin_password']);
    }

    public function test_super_admin_peut_supprimer_fournisseur_avec_bon_mdp(): void
    {
        $fournisseur = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'À supprimer',
            'adresse'                    => 'Goma',
        ]));

        $this->actingAs($this->user)
            ->delete("/fournisseurs/{$fournisseur->id}", ['admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseMissing('fournisseurs', ['id' => $fournisseur->id]);
    }

    public function test_fournisseur_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $fournisseur = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $autreEntreprise->id,
            'nom_entreprise_fournisseur' => 'Adverse',
            'adresse'                    => 'Lubumbashi',
        ]));

        $this->actingAs($this->user)
            ->delete("/fournisseurs/{$fournisseur->id}", ['admin_password' => 'password'])
            ->assertStatus(403);
    }
}
