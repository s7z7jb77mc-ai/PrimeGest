<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentWebhookNetikashTest extends TestCase
{
    use DatabaseTransactions;

    private Entreprise $entreprise;

    private Subscription $subscription;

    private string $secret = 'test-webhook-secret-32chars-ok!!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.netikash.webhook_secret' => $this->secret]);

        $this->entreprise = Entreprise::create([
            'name' => 'WebhookCorp',
            'email' => 'wh@test.com',
            'phone' => '099',
            'address' => 'Kinshasa',
            'plan' => 'free',
        ]);

        User::factory()->create([
            'email' => 'admin@wh.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->subscription = Subscription::create([
            'entreprise_id' => $this->entreprise->id,
            'plan' => 'premium',
            'amount' => 7.0,
            'payment_method' => 'netikash',
            'payment_reference' => 'PG-1-WEBHTEST',
            'status' => 'pending',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function buildPayload(array $overrides = []): array
    {
        return array_merge([
            'event' => 'payment.success',
            'ref' => 'PG-1-WEBHTEST',
            'transaction_id' => 'TXN-NETIKASH-999',
            'amount' => 7.0,
            'currency' => 'USD',
            'phone' => '243812345678',
            'status' => 'success',
        ], $overrides);
    }

    private function signedPost(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);

        return $this->postJson(
            '/api/v1/payment/webhook',
            $payload,
            [
                'X-Signature'  => $signature,
                'X-Timestamp'  => $timestamp,
            ],
        );
    }

    public function test_webhook_valide_active_subscription(): void
    {
        Mail::fake();

        $this->signedPost($this->buildPayload())
            ->assertOk()
            ->assertJsonPath('status', 'activated');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('entreprises', [
            'id' => $this->entreprise->id,
            'plan' => 'premium',
        ]);
    }

    public function test_webhook_signature_invalide_retourne_401(): void
    {
        $payload = $this->buildPayload();

        $this->postJson(
            '/api/v1/payment/webhook',
            $payload,
            ['X-Netikash-Signature' => 'mauvaise-signature'],
        )->assertStatus(401);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_deja_confirme_retourne_200_sans_doublon(): void
    {
        $this->subscription->update(['status' => 'confirmed']);

        $this->signedPost($this->buildPayload())
            ->assertOk()
            ->assertJsonPath('status', 'already_confirmed');

        $this->assertSame(
            1,
            Subscription::where('payment_reference', 'PG-1-WEBHTEST')->count()
        );
    }

    public function test_webhook_reference_inconnue_retourne_404(): void
    {
        $this->signedPost($this->buildPayload(['ref' => 'PG-99-UNKNOWN']))
            ->assertNotFound();
    }

    public function test_webhook_event_non_payment_success_est_ignore(): void
    {
        $this->signedPost($this->buildPayload(['event' => 'payment.failed']))
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'pending',
        ]);
    }
}
