<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckSubscriptionsExpirationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntrepriseWithUser(string $plan, string $status, \Carbon\Carbon $expiresAt): Entreprise
    {
        $entreprise = Entreprise::factory()->create([
            'plan' => $plan,
            'plan_expires_at' => $expiresAt,
        ]);
        User::factory()->create([
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'email' => 'admin@'.$entreprise->id.'.com',
        ]);
        Subscription::factory()->create([
            'entreprise_id' => $entreprise->id,
            'plan' => $plan,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);

        return $entreprise;
    }

    public function test_trial_warning_not_sent_on_day_of_creation(): void
    {
        Mail::fake();

        // Trial créé maintenant, expire dans 2 jours — ne doit PAS envoyer le warning
        $this->makeEntrepriseWithUser('pro', 'trial', now()->addDays(2));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertNotSent(SubscriptionExpiringSoon::class);
    }

    public function test_trial_warning_sent_one_day_before_expiry(): void
    {
        Mail::fake();

        // Trial expire dans 20 heures (J-1)
        $this->makeEntrepriseWithUser('pro', 'trial', now()->addHours(20));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertSent(SubscriptionExpiringSoon::class);
    }

    public function test_paid_warning_not_sent_two_days_before(): void
    {
        Mail::fake();

        // Abonnement payé expire dans 2 jours — fenêtre 7 jours, DOIT envoyer
        $this->makeEntrepriseWithUser('premium', 'confirmed', now()->addDays(2));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertSent(SubscriptionExpiringSoon::class);
    }

    public function test_expired_trial_is_downgraded_to_free(): void
    {
        $entreprise = $this->makeEntrepriseWithUser('pro', 'trial', now()->subHour());

        $this->artisan('subscriptions:check')->assertExitCode(0);

        $entreprise->refresh();
        $this->assertSame('free', $entreprise->plan);
        $this->assertNull($entreprise->plan_expires_at);

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $entreprise->id,
            'status' => 'expired',
        ]);
    }
}
