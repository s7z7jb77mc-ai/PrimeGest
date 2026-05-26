<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_demande_route_does_not_exist(): void
    {
        $entreprise = Entreprise::create([
            'name' => 'TestCorp',
            'email' => 'test@corp.com',
            'phone' => '0990000001',
            'address' => 'Kinshasa',
            'plan' => 'free',
        ]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post('/abonnement/demande', [
            'plan' => 'premium',
            'duree' => 1,
            'payment_method' => 'mtn',
            'payment_reference' => 'REF123',
        ]);

        $response->assertStatus(404);
    }
}
