<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests d'intégration des gates Freemium (middleware plan:feature).
 * Vérifie que le middleware CheckPlanLimit bloque correctement les
 * créations au-delà de la limite du plan free, et autorise premium/pro.
 */
class PlanLimitsIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUserWithPlan(string $plan): array
    {
        $entreprise = Entreprise::factory()->create(['plan' => $plan]);
        $user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $entreprise->update(['user_id' => $user->id]);
        Cache::forget("inertia.entreprise.{$entreprise->id}");

        return [$user, $entreprise];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clients — limite free = 20
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_plan_bloque_clients_apres_limite(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('free');
        $limit = config('plans.free.clients', 20);

        for ($i = 0; $i < $limit; $i++) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
                'uuid'             => (string) \Illuminate\Support\Str::uuid(),
                'entreprise_id'    => $entreprise->id,
                'nom_client'       => "Client {$i}",
                'numero_telephone' => '099900' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            ]));
        }

        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->post('/clients', [
                'nom_client'       => 'Client de trop',
                'numero_telephone' => '0999999999',
            ]);

        $this->assertNotSame(302, $response->status(), 'Le plan free doit bloquer au-delà de la limite clients');
    }

    public function test_premium_plan_ne_bloque_pas_clients(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('premium');

        $this->actingAs($user)
            ->post('/clients', [
                'nom_client'       => 'Client premium OK',
                'numero_telephone' => '0999888001',
            ])
            ->assertRedirect();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fournisseurs — limite free = 20
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_plan_bloque_fournisseurs_apres_limite(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('free');
        $limit = config('plans.free.fournisseurs', 20);

        for ($i = 0; $i < $limit; $i++) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => Fournisseur::create([
                'uuid'                       => (string) \Illuminate\Support\Str::uuid(),
                'entreprise_id'              => $entreprise->id,
                'nom_entreprise_fournisseur' => "Fourn {$i}",
                'adresse'                    => 'Kinshasa',
            ]));
        }

        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->post('/fournisseurs', [
                'nom_entreprise_fournisseur' => 'Fourn de trop',
                'adresse'                    => 'Goma',
            ]);

        $this->assertNotSame(302, $response->status(), 'Le plan free doit bloquer au-delà de la limite fournisseurs');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Produits — limite free = 50
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_plan_bloque_produits_apres_limite(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('free');
        $limit = config('plans.free.produits', 50);

        for ($i = 0; $i < $limit; $i++) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => Produit::create([
                'uuid'          => (string) \Illuminate\Support\Str::uuid(),
                'entreprise_id' => $entreprise->id,
                'nom'           => "Produit {$i}",
                'prix_achat'    => 100,
                'prix_vente'    => 200,
            ]));
        }

        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->post('/produits', [
                'nom'        => 'Produit de trop',
                'prix_achat' => 100,
                'prix_vente' => 200,
            ]);

        $this->assertNotSame(302, $response->status(), 'Le plan free doit bloquer au-delà de la limite produits');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dettes/créances — booléen : false en free, true en premium
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_plan_bloque_acces_creances_dettes(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('free');
        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->get('/creances-dettes');

        // Le middleware plan:dette_tracking doit bloquer (pas de redirect 302 vers success)
        $this->assertNotSame(302, $response->status());
        // Soit page Upgrade (200 Inertia) soit redirect upgrade
        $this->assertContains($response->status(), [200, 302, 403]);
    }

    public function test_premium_plan_autorise_acces_creances_dettes(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('premium');
        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->get('/creances-dettes');

        // Premium a dette_tracking: true — page accessible (200)
        $this->assertSame(200, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Succursales — false en free et premium, true en pro seulement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_succursales_requiert_plan_pro(): void
    {
        foreach (['free', 'premium'] as $plan) {
            [$user, $entreprise] = $this->makeUserWithPlan($plan);
            Cache::forget("inertia.entreprise.{$entreprise->id}");

            $response = $this->actingAs($user)
                ->post('/succursales', [
                    'nom'             => 'Test',
                    'adresse'         => 'Goma',
                    'manager_user_id' => $user->id,
                    'admin_password'  => 'password',
                ]);

            $this->assertNotSame(302, $response->status(), "Plan {$plan} ne doit pas autoriser les succursales");
        }
    }

    public function test_pro_plan_autorise_succursales(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('pro');
        Cache::forget("inertia.entreprise.{$entreprise->id}");

        // Activer multi_succursales
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => \App\Models\Parametre::firstOrCreate(
            ['entreprise_id' => $entreprise->id],
            ['multi_succursales' => true, 'nom_entreprise' => 'Pro Co']
        ));
        \App\Models\Parametre::where('entreprise_id', $entreprise->id)->update(['multi_succursales' => true]);

        $manager = User::factory()->create([
            'role'          => 'manager',
            'entreprise_id' => $entreprise->id,
            'password'      => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->postJson('/succursales', [
                'nom'             => 'Succursale Pro',
                'adresse'         => 'Goma',
                'manager_user_id' => $manager->id,
                'admin_password'  => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plan expiré → traité comme free
    // ─────────────────────────────────────────────────────────────────────────

    public function test_plan_expire_bloque_comme_free(): void
    {
        $entreprise = Entreprise::factory()->create([
            'plan'            => 'premium',
            'plan_expires_at' => now()->subDay(),
        ]);
        $user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password'      => Hash::make('password'),
        ]);
        $entreprise->update(['user_id' => $user->id]);

        // Remplir jusqu'à la limite free (clients: 20)
        $limitFree = config('plans.free.clients', 20);
        for ($i = 0; $i < $limitFree; $i++) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
                'uuid'             => (string) \Illuminate\Support\Str::uuid(),
                'entreprise_id'    => $entreprise->id,
                'nom_client'       => "Client exp {$i}",
                'numero_telephone' => '087700' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            ]));
        }

        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->post('/clients', [
                'nom_client'       => 'Client expiré refusé',
                'numero_telephone' => '0877999999',
            ]);

        $this->assertNotSame(302, $response->status(), 'Plan expiré doit être traité comme free');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Users — limite free = 3
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_plan_bloque_utilisateurs_apres_limite(): void
    {
        [$user, $entreprise] = $this->makeUserWithPlan('free');
        $limit = config('plans.free.users', 3);

        // Créer déjà limit - 1 autres utilisateurs (le $user est le 1er)
        for ($i = 1; $i < $limit; $i++) {
            User::factory()->create(['entreprise_id' => $entreprise->id]);
        }

        Cache::forget("inertia.entreprise.{$entreprise->id}");

        $response = $this->actingAs($user)
            ->post('/users', [
                'name'                  => 'User de trop',
                'email'                 => 'trop_' . uniqid() . '@test.com',
                'password'              => 'password',
                'password_confirmation' => 'password',
                'role'                  => 'user',
            ]);

        $this->assertNotSame(302, $response->status(), 'Le plan free doit bloquer au-delà de la limite users');
    }
}
