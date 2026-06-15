<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Succursale;
use App\Models\User;
use App\Services\CaisseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BusinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_sale_updates_stock_and_caisse(): void
    {
        $user = $this->createSuperAdmin();
        $produit = Produit::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Aspirine',
            'prix_achat' => 500,
            'prix_vente' => 1000,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $user->entreprise_id,
            'produit_id' => $produit->id,
            'quantite' => 20,
            'prix_achat' => 500,
            'prix_vente' => 1000,
            'total_achat' => 10000,
            'total_vente' => 20000,
            'seuil_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/mouvement-stocks', [
            'produit_id' => $produit->id,
            'type' => 'sortie',
            'quantite' => 5,
            'prix_unitaire' => 1000,
            'payment_type' => 'cash',
        ]);

        $response->assertRedirect(route('mouvement-stocks.index'));
        $this->assertDatabaseHas('stocks', [
            'entreprise_id' => $user->entreprise_id,
            'produit_id' => $produit->id,
            'quantite' => 15,
        ]);
        $this->assertDatabaseHas('caisses', [
            'entreprise_id' => $user->entreprise_id,
            'description' => 'Vente produit : Aspirine',
            'entree' => 5000,
            'sortie' => 0,
        ]);
        $this->assertDatabaseHas('journals', [
            'entreprise_id' => $user->entreprise_id,
            'produit_id' => $produit->id,
            'type' => 'entree',
            'description' => 'Vente : Aspirine - Quantité: 5',
            'montant' => 5000,
        ]);
    }

    public function test_credit_sale_updates_client_creance_without_cash_entry(): void
    {
        $user = $this->createSuperAdmin();
        $produit = Produit::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Paracetamol',
            'prix_achat' => 300,
            'prix_vente' => 700,
        ]);
        $client = Client::create([
            'entreprise_id' => $user->entreprise_id,
            'nom_client' => 'Jean Client',
            'numero_telephone' => '0999999999',
            'adresse' => 'Goma',
            'creance' => 0,
            'achat_mensuel' => 0,
            'reduction_accordee' => 0,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $user->entreprise_id,
            'produit_id' => $produit->id,
            'quantite' => 10,
            'prix_achat' => 300,
            'prix_vente' => 700,
            'total_achat' => 3000,
            'total_vente' => 7000,
            'seuil_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/mouvement-stocks', [
            'produit_id' => $produit->id,
            'type' => 'sortie',
            'quantite' => 2,
            'prix_unitaire' => 700,
            'payment_type' => 'credit',
            'client_phone' => $client->numero_telephone,
        ]);

        $response->assertRedirect(route('mouvement-stocks.index'));
        $client->refresh();

        $this->assertSame(1400.0, (float) $client->creance);
        $this->assertDatabaseMissing('caisses', [
            'entreprise_id' => $user->entreprise_id,
            'description' => 'Vente produit : Paracetamol',
            'entree' => 1400,
        ]);
    }

    public function test_credit_purchase_updates_supplier_debt_without_cash_exit(): void
    {
        $user = $this->createSuperAdmin();
        $produit = Produit::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Ibuprofene',
            'prix_achat' => 400,
            'prix_vente' => 800,
        ]);
        $fournisseur = Fournisseur::create([
            'entreprise_id' => $user->entreprise_id,
            'nom_entreprise_fournisseur' => 'Pharma Supply',
            'adresse' => 'Lubumbashi',
            'dette' => 0,
            'reduction_pourcentage' => 0,
            'achat_mensuel' => 0,
            'reduction_obtenue' => 0,
        ]);

        $response = $this->actingAs($user)->post('/mouvement-stocks/generer-bon-entree', [
            'fournisseur_id' => $fournisseur->id,
            'payment_type' => 'credit',
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'quantite' => 3,
                    'prix_unitaire' => 400,
                ],
            ],
        ]);

        $response->assertRedirect(route('mouvement-stocks.index'));
        $fournisseur->refresh();

        $this->assertSame(1200.0, (float) $fournisseur->dette);
        $this->assertDatabaseMissing('caisses', [
            'entreprise_id' => $user->entreprise_id,
            'description' => 'Achat produit : Ibuprofene',
            'sortie' => 1200,
        ]);
        $this->assertDatabaseHas('journals', [
            'entreprise_id' => $user->entreprise_id,
            'produit_id' => $produit->id,
            'type' => 'sortie',
            'description' => 'Achat à crédit : Ibuprofene - Quantité: 3',
            'montant' => 1200,
        ]);
    }

    public function test_central_cash_transfer_creates_pending_then_moves_funds_on_approval(): void
    {
        $user = $this->createSuperAdmin();
        $succursale = Succursale::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Bukavu',
            'adresse' => 'Bukavu',
            'manager_user_id' => null,
            'active' => true,
        ]);

        CaisseService::createOperation([
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => null,
            'description' => 'Solde initial central',
            'date_operation' => now(),
            'entree' => 2000000,
            'sortie' => 0,
            'type_operation' => 'initial',
        ]);

        $createResponse = $this->actingAs($user)->post('/transferts/caisse', [
            'to_succursale_id' => $succursale->id,
            'montant' => 1000000,
            'date_operation' => '2026-04-09T21:19',
        ]);

        $createResponse->assertRedirect(route('transferts.index'));
        $transfertId = DB::table('transferts')->max('id');

        $this->assertDatabaseHas('transferts', [
            'id' => $transfertId,
            'entreprise_id' => $user->entreprise_id,
            'from_succursale_id' => null,
            'to_succursale_id' => $succursale->id,
            'status' => 'pending',
        ]);

        $approveResponse = $this->actingAs($user)->post("/transferts/{$transfertId}/approve", [
            'admin_password' => 'password',
        ]);

        $approveResponse->assertRedirect(route('transferts.index'));
        $this->assertDatabaseHas('transferts', [
            'id' => $transfertId,
            'status' => 'validated',
        ]);
        $this->assertDatabaseHas('caisses', [
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => null,
            'sortie' => 1000000,
            'type_operation' => 'transfert_out',
        ]);
        $this->assertDatabaseHas('caisses', [
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => $succursale->id,
            'entree' => 1000000,
            'type_operation' => 'transfert_in',
        ]);
    }

    public function test_stock_transfer_moves_stock_without_creating_cash_operation(): void
    {
        $user = $this->createSuperAdmin();
        $source = Succursale::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Goma',
            'adresse' => 'Goma',
            'manager_user_id' => null,
            'active' => true,
        ]);
        $destination = Succursale::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Bukavu',
            'adresse' => 'Bukavu',
            'manager_user_id' => null,
            'active' => true,
        ]);
        $produit = Produit::create([
            'entreprise_id' => $user->entreprise_id,
            'nom' => 'Vitamine C',
            'prix_achat' => 200,
            'prix_vente' => 500,
        ]);

        DB::table('stocks')->insert([
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => $source->id,
            'produit_id' => $produit->id,
            'quantite' => 25,
            'prix_achat' => 200,
            'prix_vente' => 500,
            'total_achat' => 5000,
            'total_vente' => 12500,
            'seuil_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->actingAs($user)
            ->withSession(['succursale_id' => $source->id])
            ->post('/transferts/stock', [
                'to_succursale_id' => $destination->id,
                'produit_id' => $produit->id,
                'quantite' => 10,
                'date_operation' => '2026-04-09T22:00',
            ]);

        $createResponse->assertRedirect(route('transferts.index'));
        $transfertId = DB::table('transferts')->max('id');

        $approveResponse = $this->actingAs($user)->post("/transferts/{$transfertId}/approve", [
            'admin_password' => 'password',
        ]);

        $approveResponse->assertRedirect(route('transferts.index'));
        $this->assertDatabaseHas('stocks', [
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => $source->id,
            'produit_id' => $produit->id,
            'quantite' => 15,
        ]);
        $this->assertDatabaseHas('stocks', [
            'entreprise_id' => $user->entreprise_id,
            'succursale_id' => $destination->id,
            'produit_id' => $produit->id,
            'quantite' => 10,
        ]);
        $this->assertDatabaseCount('caisses', 0);
    }

    private function createSuperAdmin(): User
    {
        $entreprise = Entreprise::create([
            'name'            => 'PrimeGest Test',
            'email'           => 'entreprise@example.com',
            'phone'           => '0990000000',
            'address'         => 'Lubumbashi',
            'plan'            => 'pro',
            'plan_expires_at' => now()->addDays(30),
        ]);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $entreprise->update(['user_id' => $user->id]);

        return $user;
    }
}
