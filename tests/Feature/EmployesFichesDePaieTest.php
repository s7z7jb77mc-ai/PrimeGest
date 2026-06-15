<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employe;
use App\Models\Entreprise;
use App\Models\FicheDePaie;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployesFichesDePaieTest extends TestCase
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

    private function makeEmploye(array $overrides = []): Employe
    {
        return \Illuminate\Database\Eloquent\Model::unguarded(fn () => Employe::create(array_merge([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Jean',
            'prenom'        => 'Dupont',
            'email'         => \Illuminate\Support\Str::uuid() . '@test.com',
            'telephone'     => '0999' . rand(100000, 999999),
            'poste'         => 'Technicien',
            'salaire_base'  => 500.00,
            'statut'        => 'actif',
        ], $overrides)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMPLOYÉS — store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_creer_employe(): void
    {
        $email = 'employe_' . uniqid() . '@primegest.test';

        $this->actingAs($this->user)
            ->post('/employes', [
                'nom'          => 'Alice',
                'prenom'       => 'Martin',
                'email'        => $email,
                'telephone'    => '0999100100',
                'poste'        => 'Comptable',
                'salaire_base' => 800.00,
                'statut'       => 'actif',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employes', [
            'nom'           => 'Alice',
            'email'         => $email,
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    public function test_employe_store_requiert_auth(): void
    {
        $this->post('/employes', ['nom' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_employe_store_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->post('/employes', [])
            ->assertSessionHasErrors(['nom', 'email', 'salaire_base', 'statut']);
    }

    public function test_employe_email_unique(): void
    {
        $email = 'unique_' . uniqid() . '@primegest.test';
        $this->makeEmploye(['email' => $email]);

        $this->actingAs($this->user)
            ->post('/employes', [
                'nom'          => 'Doublon',
                'email'        => $email,
                'salaire_base' => 500,
                'statut'       => 'actif',
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_employe_statut_invalide_rejete(): void
    {
        $this->actingAs($this->user)
            ->post('/employes', [
                'nom'          => 'Test',
                'email'        => 'test_' . uniqid() . '@test.com',
                'salaire_base' => 500,
                'statut'       => 'zombie',
            ])
            ->assertSessionHasErrors(['statut']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMPLOYÉS — update / destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_employe_avec_bon_mdp(): void
    {
        $employe = $this->makeEmploye(['nom' => 'Avant', 'email' => 'avant_' . uniqid() . '@test.com']);

        $this->actingAs($this->user)
            ->put("/employes/{$employe->id}", [
                'nom'            => 'Après',
                'email'          => $employe->email,
                'salaire_base'   => 600,
                'statut'         => 'actif',
                'admin_password' => 'password',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employes', ['id' => $employe->id, 'nom' => 'Après']);
    }

    public function test_modification_employe_echoue_mauvais_mdp(): void
    {
        $employe = $this->makeEmploye(['nom' => 'Original', 'email' => 'original_' . uniqid() . '@test.com']);

        $this->actingAs($this->user)
            ->put("/employes/{$employe->id}", [
                'nom'            => 'Hack',
                'email'          => $employe->email,
                'salaire_base'   => 600,
                'statut'         => 'actif',
                'admin_password' => 'wrong',
            ])
            ->assertSessionHasErrors(['admin_password']);
    }

    public function test_super_admin_peut_supprimer_employe_avec_bon_mdp(): void
    {
        $employe = $this->makeEmploye(['email' => 'del_' . uniqid() . '@test.com']);

        $this->actingAs($this->user)
            ->delete("/employes/{$employe->id}", ['admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseMissing('employes', ['id' => $employe->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FICHES DE PAIE — store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_peut_creer_fiche_de_paie(): void
    {
        $employe = $this->makeEmploye(['salaire_base' => 1000.00, 'email' => 'fiche_' . uniqid() . '@test.com']);

        $this->actingAs($this->user)
            ->post('/fiches', [
                'employe_id'    => $employe->id,
                'mois'          => 5,
                'annee'         => 2026,
                'primes'        => 100,
                'retenues'      => 50,
                'statut'        => 'en_attente',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fiches_de_paie', [
            'employe_id'    => $employe->id,
            'mois'          => 5,
            'annee'         => 2026,
            'salaire_base'  => 1000.00,
            'net_a_payer'   => 1050.00, // 1000 + 100 - 50
        ]);
    }

    public function test_fiche_store_requiert_auth(): void
    {
        $this->post('/fiches', [])->assertRedirect('/login');
    }

    public function test_fiche_store_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->post('/fiches', [])
            ->assertSessionHasErrors(['employe_id', 'mois', 'annee', 'statut']);
    }

    public function test_fiche_mois_invalide_rejete(): void
    {
        $employe = $this->makeEmploye(['email' => 'mois_' . uniqid() . '@test.com']);

        $this->actingAs($this->user)
            ->post('/fiches', [
                'employe_id' => $employe->id,
                'mois'       => 13,
                'annee'      => 2026,
                'statut'     => 'en_attente',
            ])
            ->assertSessionHasErrors(['mois']);
    }

    public function test_fiche_employe_autre_entreprise_rejete(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreEmploye = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Employe::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'nom'           => 'Adverse',
            'email'         => 'adverse_' . uniqid() . '@test.com',
            'salaire_base'  => 500,
            'statut'        => 'actif',
        ]));

        $this->actingAs($this->user)
            ->post('/fiches', [
                'employe_id' => $autreEmploye->id,
                'mois'       => 1,
                'annee'      => 2026,
                'statut'     => 'en_attente',
            ])
            ->assertSessionHasErrors(['employe_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FICHES DE PAIE — update / destroy
    // ─────────────────────────────────────────────────────────────────────────

    private function makeFiche(Employe $employe, array $overrides = []): FicheDePaie
    {
        return \Illuminate\Database\Eloquent\Model::unguarded(fn () => FicheDePaie::create(array_merge([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $this->entreprise->id,
            'employe_id'    => $employe->id,
            'mois'          => 1,
            'annee'         => 2026,
            'salaire_base'  => $employe->salaire_base,
            'primes'        => 0,
            'retenues'      => 0,
            'net_a_payer'   => $employe->salaire_base,
            'statut'        => 'en_attente',
        ], $overrides)));
    }

    public function test_peut_modifier_fiche(): void
    {
        $employe = $this->makeEmploye(['email' => 'update_' . uniqid() . '@test.com']);
        $fiche   = $this->makeFiche($employe);

        $this->actingAs($this->user)
            ->put("/fiches/{$fiche->id}", [
                'primes'   => 200,
                'retenues' => 100,
                'statut'   => 'paye',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fiches_de_paie', [
            'id'          => $fiche->id,
            'primes'      => 200,
            'net_a_payer' => 600.00, // 500 + 200 - 100
            'statut'      => 'paye',
        ]);
    }

    public function test_modification_fiche_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreUser = User::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
            'role'          => 'super_admin',
        ]);
        $autreEmploye = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Employe::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'nom'           => 'Adverse',
            'email'         => 'adv2_' . uniqid() . '@test.com',
            'salaire_base'  => 500,
            'statut'        => 'actif',
        ]));
        $autreFiche = \Illuminate\Database\Eloquent\Model::unguarded(fn () => FicheDePaie::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'employe_id'    => $autreEmploye->id,
            'mois'          => 1,
            'annee'         => 2026,
            'salaire_base'  => 500,
            'net_a_payer'   => 500,
            'statut'        => 'en_attente',
        ]));

        $this->actingAs($this->user)
            ->put("/fiches/{$autreFiche->id}", ['statut' => 'payé'])
            ->assertStatus(403);
    }

    public function test_peut_supprimer_fiche(): void
    {
        $employe = $this->makeEmploye(['email' => 'del2_' . uniqid() . '@test.com']);
        $fiche   = $this->makeFiche($employe);

        $this->actingAs($this->user)
            ->delete("/fiches/{$fiche->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('fiches_de_paie', ['id' => $fiche->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FICHES DE PAIE — confirmerPaiement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_confirmer_paiement_met_statut_payee(): void
    {
        $employe = $this->makeEmploye(['email' => 'conf_' . uniqid() . '@test.com']);
        $fiche   = $this->makeFiche($employe);

        $this->actingAs($this->user)
            ->put("/fiches/{$fiche->id}/confirmer")
            ->assertRedirect();

        $this->assertDatabaseHas('fiches_de_paie', [
            'id'              => $fiche->id,
            'statut_paiement' => 'payee',
        ]);
    }

    public function test_confirmer_paiement_fiche_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $autreEmploye = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Employe::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'nom'           => 'Adverse',
            'email'         => 'adv3_' . uniqid() . '@test.com',
            'salaire_base'  => 500,
            'statut'        => 'actif',
        ]));
        $autreFiche = \Illuminate\Database\Eloquent\Model::unguarded(fn () => FicheDePaie::create([
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id' => $autreEntreprise->id,
            'employe_id'    => $autreEmploye->id,
            'mois'          => 2,
            'annee'         => 2026,
            'salaire_base'  => 500,
            'net_a_payer'   => 500,
            'statut'        => 'en_attente',
        ]));

        $this->actingAs($this->user)
            ->put("/fiches/{$autreFiche->id}/confirmer")
            ->assertStatus(403);
    }
}
