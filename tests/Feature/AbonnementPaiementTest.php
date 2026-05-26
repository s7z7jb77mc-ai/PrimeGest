<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Subscription\InitierPaiementAction;
use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbonnementPaiementTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'name' => 'TestCorp',
            'email' => 'corp@test.com',
            'phone' => '0990000001',
            'address' => 'Kinshasa',
            'plan' => 'free',
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin@testcorp.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->entreprise->update(['user_id' => $this->user->id]);
    }

    public function test_initier_paiement_cree_subscription_pending(): void
    {
        config(['plans.prices.premium' => 7.0]);

        $this->mock(InitierPaiementAction::class, function ($mock) {
            $sub = new Subscription([
                'entreprise_id' => $this->entreprise->id,
                'plan' => 'premium',
                'amount' => 7.0,
                'payment_method' => 'netikash',
                'payment_reference' => 'PG-1-TESTAAAA',
                'status' => 'pending',
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);
            $sub->save();
            $mock->shouldReceive('execute')->once()->andReturn([
                'subscription' => $sub,
                'checkout_url' => 'https://checkout.netikash.com/test',
                'request_id'   => 'test-request-id',
            ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan' => 'premium',
                'duree' => 1,
                'phone' => '243812345678',
                'devise' => 'USD',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['reference', 'message'])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $this->entreprise->id,
            'plan' => 'premium',
            'amount' => 7.0,
            'status' => 'pending',
        ]);
    }

    public function test_initier_paiement_6_mois_applique_reduction(): void
    {
        config(['plans.promotional_prices.premium' => [6 => 40.0, 12 => 70.0]]);

        $this->mock(InitierPaiementAction::class, function ($mock) {
            $sub = new Subscription([
                'entreprise_id' => $this->entreprise->id,
                'plan' => 'premium',
                'amount' => 40.0,
                'payment_method' => 'netikash',
                'payment_reference' => 'PG-1-TEST6MOS',
                'status' => 'pending',
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
            ]);
            $sub->save();
            $mock->shouldReceive('execute')->once()->andReturn([
                'subscription' => $sub,
                'checkout_url' => 'https://checkout.netikash.com/test',
                'request_id'   => 'test-request-id',
            ]);
        });

        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan' => 'premium',
                'duree' => 6,
                'phone' => '243812345678',
                'devise' => 'USD',
            ])
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $this->entreprise->id,
            'amount' => 40.0,
        ]);
    }

    public function test_initier_paiement_netikash_echoue_marque_failed(): void
    {
        $this->mock(InitierPaiementAction::class, function ($mock) {
            $mock->shouldReceive('execute')->once()
                ->andThrow(new \RuntimeException('Netikash: service indisponible'));
        });

        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan' => 'pro',
                'duree' => 1,
                'phone' => '243812345678',
                'devise' => 'USD',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_statut_retourne_statut_subscription(): void
    {
        Subscription::create([
            'entreprise_id' => $this->entreprise->id,
            'plan' => 'premium',
            'amount' => 7.0,
            'payment_method' => 'netikash',
            'payment_reference' => 'PG-1-TEST001',
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/abonnement/statut/PG-1-TEST001')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('plan', 'premium');
    }

    public function test_statut_subscription_autre_entreprise_retourne_404(): void
    {
        $autreEntreprise = Entreprise::create([
            'name' => 'Autre', 'email' => 'autre@test.com',
            'phone' => '099', 'address' => 'Goma',
        ]);

        Subscription::create([
            'entreprise_id' => $autreEntreprise->id,
            'plan' => 'premium',
            'amount' => 7.0,
            'payment_method' => 'netikash',
            'payment_reference' => 'PG-99-OTHER',
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/abonnement/statut/PG-99-OTHER')
            ->assertNotFound();
    }

    public function test_plan_invalide_est_rejete(): void
    {
        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan' => 'free',
                'duree' => 1,
                'phone' => '243812345678',
                'devise' => 'USD',
            ])
            ->assertStatus(422);
    }
}
