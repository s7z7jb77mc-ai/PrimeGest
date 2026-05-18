<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_entreprise_starts_on_pro_trial(): void
    {
        $response = $this->post('/register-entreprise', [
            'entreprise_name' => 'Test Corp',
            'entreprise_email' => 'corp@test.com',
            'entreprise_phone' => '0990000000',
            'entreprise_address' => 'Lubumbashi',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin@test.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        $entreprise = Entreprise::where('email', 'corp@test.com')->firstOrFail();

        $this->assertSame('pro', $entreprise->plan);
        $this->assertNotNull($entreprise->plan_expires_at);
        $this->assertTrue($entreprise->plan_expires_at->isFuture());
        $this->assertEqualsWithDelta(2, now()->diffInDays($entreprise->plan_expires_at), 0.1);
    }

    public function test_new_entreprise_trial_creates_subscription_record(): void
    {
        $this->post('/register-entreprise', [
            'entreprise_name' => 'Trial Corp',
            'entreprise_email' => 'trial@corp.com',
            'entreprise_phone' => '0990000001',
            'entreprise_address' => 'Kinshasa',
            'admin_name' => 'Admin Trial',
            'admin_email' => 'admin@trial.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ]);

        $entreprise = Entreprise::where('email', 'trial@corp.com')->firstOrFail();

        $subscription = Subscription::where('entreprise_id', $entreprise->id)->firstOrFail();

        $this->assertSame('pro', $subscription->plan);
        $this->assertSame('trial', $subscription->status);
        $this->assertSame(0.0, (float) $subscription->amount);
        $this->assertSame('trial', $subscription->payment_method);
    }
}
