<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FactureGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generer_facture_creates_facture_and_decrements_stock(): void
    {
        [$user, $entreprise] = $this->createUserWithEntreprise();

        $produit = Produit::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Produit Test',
            'prix_achat' => 100,
            'prix_vente' => 150,
        ]);

        Stock::create([
            'entreprise_id' => $entreprise->id,
            'produit_id' => $produit->id,
            'quantite' => 10,
            'prix_achat' => 100,
            'prix_vente' => 150,
            'total_achat' => 1000,
            'total_vente' => 1500,
        ]);

        $response = $this->actingAs($user)->post('/mouvement-stocks/generer-facture', [
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'quantite' => 2,
                    'prix_unitaire' => 150,
                ],
            ],
        ]);

        $facture = Facture::first();

        $this->assertNotNull($facture);
        $response->assertRedirect(route('factures.show', $facture->id, false));

        $this->assertDatabaseHas('facture_lignes', [
            'facture_id' => $facture->id,
            'produit_id' => $produit->id,
            'quantite' => 2,
        ]);

        $this->assertDatabaseHas('stocks', [
            'entreprise_id' => $entreprise->id,
            'produit_id' => $produit->id,
            'quantite' => 8,
        ]);
    }

    public function test_generer_facture_fails_with_clear_error_when_stock_is_insufficient(): void
    {
        [$user, $entreprise] = $this->createUserWithEntreprise();

        $produit = Produit::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Produit Test',
            'prix_achat' => 100,
            'prix_vente' => 150,
        ]);

        Stock::create([
            'entreprise_id' => $entreprise->id,
            'produit_id' => $produit->id,
            'quantite' => 1,
            'prix_achat' => 100,
            'prix_vente' => 150,
            'total_achat' => 100,
            'total_vente' => 150,
        ]);

        $response = $this->actingAs($user)->from('/mouvement-stocks')->post('/mouvement-stocks/generer-facture', [
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'quantite' => 5,
                    'prix_unitaire' => 150,
                ],
            ],
        ]);

        $response->assertRedirect('/mouvement-stocks');
        $response->assertSessionHasErrors('quantite');
        $this->assertDatabaseCount('factures', 0);
    }

    private function createUserWithEntreprise(): array
    {
        $user = User::factory()->create();

        $entreprise = Entreprise::create([
            'name' => 'Entreprise Test',
            'uuid' => (string) Str::uuid(),
            'slug' => 'entreprise-test',
            'email' => 'entreprise-test@example.com',
            'phone' => '000000',
            'address' => 'Adresse Test',
            'user_id' => $user->id,
        ]);

        $user->entreprise_id = $entreprise->id;
        $user->save();

        return [$user, $entreprise];
    }
}
