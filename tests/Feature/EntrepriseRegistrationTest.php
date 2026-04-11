<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntrepriseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entreprise_registration_creates_user_with_entreprise_id(): void
    {
        $response = $this->post('/register-entreprise', [
            'entreprise_name' => 'Test Company',
            'entreprise_email' => 'company@test.com',
            'entreprise_phone' => '123456789',
            'entreprise_address' => '123 Test Street',
            'admin_name' => 'Test Admin',
            'admin_email' => 'admin@test.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        // Vérifier que l'utilisateur a été créé avec entreprise_id
        $user = User::where('email', 'admin@test.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->entreprise_id);

        // Vérifier que l'entreprise a été créée
        $entreprise = Entreprise::where('email', 'company@test.com')->first();
        $this->assertNotNull($entreprise);
        $this->assertEquals($user->entreprise_id, $entreprise->id);
    }
}
