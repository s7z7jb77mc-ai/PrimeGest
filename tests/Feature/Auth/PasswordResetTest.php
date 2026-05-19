<?php

namespace Tests\Feature\Auth;

use App\Models\Entreprise;
use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        [$user, $entreprise] = $this->createSuperAdminWithEntreprise();

        $this->post('/forgot-password', [
            'email' => $user->email,
            'company_name' => $entreprise->name,
        ]);

        Notification::assertSentTo($user, CustomResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        [$user, $entreprise] = $this->createSuperAdminWithEntreprise();

        $this->post('/forgot-password', [
            'email' => $user->email,
            'company_name' => $entreprise->name,
        ]);

        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        [$user, $entreprise] = $this->createSuperAdminWithEntreprise();

        $this->post('/forgot-password', [
            'email' => $user->email,
            'company_name' => $entreprise->name,
        ]);

        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use ($user, $entreprise) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'company_name' => $entreprise->name,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    private function createSuperAdminWithEntreprise(): array
    {
        $entreprise = Entreprise::create([
            'name' => 'PrimeGest Test',
            'email' => 'test@primegest.app',
        ]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password' => Hash::make('password'),
        ]);

        return [$user, $entreprise];
    }
}
