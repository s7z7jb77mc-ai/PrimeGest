<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Caisse;
use App\Models\Client;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests du module Créances/Dettes.
 * Requiert plan:dette_tracking (premium ou pro).
 */
class CreancesDettesTest extends TestCase
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
        Cache::forget("inertia.entreprise.{$this->entreprise->id}");
    }

    private function makeClient(float $creance = 0.0): Client
    {
        return \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $this->entreprise->id,
            'nom_client'       => 'Client créance ' . uniqid(),
            'numero_telephone' => '0998' . rand(100000, 999999),
            'creance'          => $creance,
        ]));
    }

    /** Crée une entrée caisse pour fournir un solde positif (nécessaire avant un paiement de dette). */
    private function seedCaisseBalance(float $montant): void
    {
        Model::unguarded(fn () => Caisse::create([
            'entreprise_id'   => $this->entreprise->id,
            'description'     => 'Solde initial test',
            'date_operation'  => now(),
            'entree'          => $montant,
            'sortie'          => 0,
            'solde'           => $montant,
        ]));
    }

    private function makeFournisseur(float $dette = 0.0): Fournisseur
    {
        return \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Fourn dette ' . uniqid(),
            'adresse'                    => 'Goma',
            'dette'                      => $dette,
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index — accès
    // ─────────────────────────────────────────────────────────────────────────

    public function test_index_accessible_plan_premium(): void
    {
        $this->actingAs($this->user)
            ->get('/creances-dettes')
            ->assertOk();
    }

    public function test_index_bloque_plan_free(): void
    {
        $entrepriseFree = Entreprise::factory()->create(['plan' => 'free']);
        $userFree = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entrepriseFree->id,
        ]);
        $entrepriseFree->update(['user_id' => $userFree->id]);
        Cache::forget("inertia.entreprise.{$entrepriseFree->id}");

        $response = $this->actingAs($userFree)->get('/creances-dettes');
        // Le middleware plan:dette_tracking doit bloquer
        $this->assertNotSame(200, $response->status());
    }

    public function test_index_requiert_auth(): void
    {
        $this->get('/creances-dettes')->assertRedirect('/login');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // payerCreance
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payer_creance_reduit_montant_client(): void
    {
        $client = $this->makeClient(creance: 500.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/clients/{$client->id}/paiement", [
                'montant' => 200,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'id'      => $client->id,
            'creance' => 300.0,
        ]);
    }

    public function test_payer_creance_cree_entree_caisse(): void
    {
        $client = $this->makeClient(creance: 1000.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/clients/{$client->id}/paiement", [
                'montant' => 500,
            ])
            ->assertRedirect();

        // Une entrée de caisse doit être créée
        $this->assertDatabaseHas('caisses', [
            'entreprise_id' => $this->entreprise->id,
            'entree'        => 500,
        ]);
    }

    public function test_payer_creance_montant_superieur_rejete(): void
    {
        $client = $this->makeClient(creance: 100.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/clients/{$client->id}/paiement", [
                'montant' => 200,
            ])
            ->assertSessionHasErrors(['montant']);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'creance' => 100.0]);
    }

    public function test_payer_creance_montant_negatif_rejete(): void
    {
        $client = $this->makeClient(creance: 500.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/clients/{$client->id}/paiement", [
                'montant' => -50,
            ])
            ->assertSessionHasErrors(['montant']);
    }

    public function test_payer_creance_client_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreClient = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'    => $autreEntreprise->id,
            'nom_client'       => 'Adverse',
            'numero_telephone' => '0997001001',
            'creance'          => 500,
        ]));

        $this->actingAs($this->user)
            ->post("/creances-dettes/clients/{$autreClient->id}/paiement", [
                'montant' => 100,
            ])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // payerDette
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payer_dette_reduit_montant_fournisseur(): void
    {
        $this->seedCaisseBalance(1000.0); // Le paiement d'une dette débite la caisse
        $fourn = $this->makeFournisseur(dette: 800.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/fournisseurs/{$fourn->id}/paiement", [
                'montant' => 300,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fournisseurs', [
            'id'    => $fourn->id,
            'dette' => 500.0,
        ]);
    }

    public function test_payer_dette_cree_sortie_caisse(): void
    {
        $this->seedCaisseBalance(1000.0);
        $fourn = $this->makeFournisseur(dette: 600.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/fournisseurs/{$fourn->id}/paiement", [
                'montant' => 250,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('caisses', [
            'entreprise_id' => $this->entreprise->id,
            'sortie'        => 250,
        ]);
    }

    public function test_payer_dette_montant_superieur_rejete(): void
    {
        $fourn = $this->makeFournisseur(dette: 100.0);

        $this->actingAs($this->user)
            ->post("/creances-dettes/fournisseurs/{$fourn->id}/paiement", [
                'montant' => 150,
            ])
            ->assertSessionHasErrors(['montant']);
    }

    public function test_payer_dette_fournisseur_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreFourn = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'              => $autreEntreprise->id,
            'nom_entreprise_fournisseur' => 'Adverse SARL',
            'adresse'                    => 'Lubumbashi',
            'dette'                      => 400,
        ]));

        $this->actingAs($this->user)
            ->post("/creances-dettes/fournisseurs/{$autreFourn->id}/paiement", [
                'montant' => 100,
            ])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detail views
    // ─────────────────────────────────────────────────────────────────────────

    public function test_detail_client_accessible(): void
    {
        $client = $this->makeClient(creance: 200.0);

        $this->actingAs($this->user)
            ->get("/creances-dettes/clients/{$client->id}")
            ->assertOk();
    }

    public function test_detail_fournisseur_accessible(): void
    {
        $fourn = $this->makeFournisseur(dette: 300.0);

        $this->actingAs($this->user)
            ->get("/creances-dettes/fournisseurs/{$fourn->id}")
            ->assertOk();
    }
}
