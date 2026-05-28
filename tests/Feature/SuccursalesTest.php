<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Succursale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests CRUD Succursales.
 * Requiert plan:succursales (pro), mot de passe super_admin,
 * et multi_succursales activé dans les paramètres.
 */
class SuccursalesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::factory()->create(['plan' => 'pro']);
        $this->user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $this->entreprise->update(['user_id' => $this->user->id]);

        // Activer multi_succursales dans les paramètres
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Parametre::firstOrCreate(
            ['entreprise_id' => $this->entreprise->id],
            ['multi_succursales' => true, 'nom_entreprise' => 'Test Co']
        ));
        Parametre::where('entreprise_id', $this->entreprise->id)
            ->update(['multi_succursales' => true]);
    }

    private function makeManager(): User
    {
        return User::factory()->create([
            'role'          => 'manager',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);
    }

    private function makeSuccursale(array $overrides = []): Succursale
    {
        $manager = $this->makeManager();
        return \Illuminate\Database\Eloquent\Model::unguarded(fn () => Succursale::create(array_merge([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'   => $this->entreprise->id,
            'nom'             => 'Succursale Test ' . uniqid(),
            'adresse'         => 'Kinshasa, Gombe',
            'manager_user_id' => $manager->id,
            'active'          => true,
        ], $overrides)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_creer_succursale_avec_bon_mdp(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($this->user)
            ->postJson('/succursales', [
                'nom'             => 'Succursale Goma',
                'adresse'         => 'Goma, Nord-Kivu',
                'manager_user_id' => $manager->id,
                'active'          => true,
                'admin_password'  => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('succursales', [
            'nom'           => 'Succursale Goma',
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    public function test_creation_succursale_echoue_mauvais_mdp(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($this->user)
            ->postJson('/succursales', [
                'nom'             => 'Succursale X',
                'adresse'         => 'Lubumbashi',
                'manager_user_id' => $manager->id,
                'admin_password'  => 'wrong',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_password']);
    }

    public function test_non_super_admin_ne_peut_pas_creer_succursale(): void
    {
        $autreUser = User::factory()->create([
            'role'          => 'user',
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->actingAs($autreUser)
            ->postJson('/succursales', [
                'nom'             => 'Hack Succursale',
                'adresse'         => 'Goma',
                'manager_user_id' => $autreUser->id,
                'admin_password'  => 'password',
            ])
            ->assertStatus(403);
    }

    public function test_creation_succursale_requiert_auth(): void
    {
        $this->postJson('/succursales', ['nom' => 'Test'])
            ->assertStatus(401);
    }

    public function test_creation_succursale_valide_champs_requis(): void
    {
        $this->actingAs($this->user)
            ->postJson('/succursales', ['admin_password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nom', 'adresse', 'manager_user_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // plan:succursales gate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_plan_free_bloque_creation_succursale(): void
    {
        $entrepriseFree = Entreprise::factory()->create(['plan' => 'free']);
        $userFree = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entrepriseFree->id,
            'password'      => Hash::make('password'),
        ]);
        $entrepriseFree->update(['user_id' => $userFree->id]);

        \Illuminate\Support\Facades\Cache::forget("inertia.entreprise.{$entrepriseFree->id}");

        $response = $this->actingAs($userFree)
            ->post('/succursales', [
                'nom'             => 'Succursale X',
                'adresse'         => 'Goma',
                'manager_user_id' => $userFree->id,
                'admin_password'  => 'password',
            ]);

        // Doit être bloqué (403 ou Inertia Upgrade page)
        $this->assertNotSame(302, $response->status(), 'Le plan free ne doit pas autoriser les succursales');
    }

    public function test_plan_premium_bloque_aussi_succursale(): void
    {
        $entreprisePremium = Entreprise::factory()->create(['plan' => 'premium']);
        $userPremium = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprisePremium->id,
            'password'      => Hash::make('password'),
        ]);
        $entreprisePremium->update(['user_id' => $userPremium->id]);

        \Illuminate\Support\Facades\Cache::forget("inertia.entreprise.{$entreprisePremium->id}");

        $response = $this->actingAs($userPremium)
            ->post('/succursales', [
                'nom'             => 'Succursale Premium',
                'adresse'         => 'Kinshasa',
                'manager_user_id' => $userPremium->id,
                'admin_password'  => 'password',
            ]);

        // Premium n'inclut pas succursales
        $this->assertNotSame(302, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_succursale_avec_bon_mdp(): void
    {
        $succursale = $this->makeSuccursale(['nom' => 'Avant']);
        $manager    = $this->makeManager();

        $this->actingAs($this->user)
            ->putJson("/succursales/{$succursale->id}", [
                'nom'             => 'Après',
                'adresse'         => 'Goma',
                'manager_user_id' => $manager->id,
                'admin_password'  => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('succursales', ['id' => $succursale->id, 'nom' => 'Après']);
    }

    public function test_modification_succursale_bloquee_mauvais_mdp(): void
    {
        $succursale = $this->makeSuccursale();
        $manager    = $this->makeManager();

        $this->actingAs($this->user)
            ->putJson("/succursales/{$succursale->id}", [
                'nom'             => 'Hack',
                'adresse'         => 'Goma',
                'manager_user_id' => $manager->id,
                'admin_password'  => 'wrong',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_password']);
    }

    public function test_modification_succursale_autre_entreprise_retourne_403(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['plan' => 'pro']);
        $autreSuccursale = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Succursale::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'   => $autreEntreprise->id,
            'nom'             => 'Adverse',
            'adresse'         => 'Lubumbashi',
            'manager_user_id' => User::factory()->create(['entreprise_id' => $autreEntreprise->id])->id,
        ]));

        $this->actingAs($this->user)
            ->putJson("/succursales/{$autreSuccursale->id}", [
                'nom'             => 'Hack',
                'adresse'         => 'Goma',
                'manager_user_id' => $this->user->id,
                'admin_password'  => 'password',
            ])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_peut_supprimer_succursale_avec_bon_mdp(): void
    {
        $succursale = $this->makeSuccursale();

        $this->actingAs($this->user)
            ->deleteJson("/succursales/{$succursale->id}", ['admin_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('succursales', ['id' => $succursale->id]);
    }

    public function test_suppression_succursale_bloquee_mauvais_mdp(): void
    {
        $succursale = $this->makeSuccursale();

        $this->actingAs($this->user)
            ->deleteJson("/succursales/{$succursale->id}", ['admin_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_password']);

        $this->assertDatabaseHas('succursales', ['id' => $succursale->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show / exit (session)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_show_succursale_definit_session_et_redirige(): void
    {
        $succursale = $this->makeSuccursale();

        $this->actingAs($this->user)
            ->get("/succursales/{$succursale->id}")
            ->assertRedirect('/dashboard');

        $this->assertEquals($succursale->id, session('succursale_id'));
    }

    public function test_exit_succursale_vide_la_session(): void
    {
        session(['succursale_id' => 999]);

        $this->actingAs($this->user)
            ->get('/succursales-exit')
            ->assertRedirect('/dashboard');

        $this->assertNull(session('succursale_id'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Restriction après rétrogradation Pro → Premium
    // ─────────────────────────────────────────────────────────────────────────

    public function test_plan_premium_bloque_index_succursales(): void
    {
        $entreprisePremium = Entreprise::factory()->create(['plan' => 'premium']);
        $userPremium = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprisePremium->id,
        ]);
        $entreprisePremium->update(['user_id' => $userPremium->id]);
        \Illuminate\Support\Facades\Cache::forget("inertia.entreprise.{$entreprisePremium->id}");

        $response = $this->actingAs($userPremium)->get('/succursales');

        // Le middleware plan:succursales doit bloquer — ni 200 ni 302 vers dashboard
        $this->assertNotSame(200, $response->status());
    }

    public function test_plan_premium_bloque_show_succursale(): void
    {
        // Succursale appartenant à une entreprise pro tierce — on veut juste tester le gate
        $autreEntreprisePro = Entreprise::factory()->create(['plan' => 'pro']);
        $succursale = \Illuminate\Database\Eloquent\Model::unguarded(fn () => Succursale::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'entreprise_id'   => $autreEntreprisePro->id,
            'nom'             => 'Succursale Pro',
            'adresse'         => 'Goma',
            'manager_user_id' => User::factory()->create(['entreprise_id' => $autreEntreprisePro->id])->id,
        ]));

        $entreprisePremium = Entreprise::factory()->create(['plan' => 'premium']);
        $userPremium = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprisePremium->id,
        ]);
        $entreprisePremium->update(['user_id' => $userPremium->id]);
        \Illuminate\Support\Facades\Cache::forget("inertia.entreprise.{$entreprisePremium->id}");

        $response = $this->actingAs($userPremium)->get("/succursales/{$succursale->id}");

        $this->assertNotSame(302, $response->status());
    }

    public function test_exit_succursale_reste_accessible_apres_retrograde(): void
    {
        // Un utilisateur dont le plan est passé à premium doit pouvoir sortir du contexte succursale
        $entreprisePremium = Entreprise::factory()->create(['plan' => 'premium']);
        $userPremium = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprisePremium->id,
        ]);
        $entreprisePremium->update(['user_id' => $userPremium->id]);
        \Illuminate\Support\Facades\Cache::forget("inertia.entreprise.{$entreprisePremium->id}");

        session(['succursale_id' => 42]);

        $this->actingAs($userPremium)
            ->get('/succursales-exit')
            ->assertRedirect('/dashboard');
    }
}
