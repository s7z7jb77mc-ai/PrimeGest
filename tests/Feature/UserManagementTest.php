<?php

namespace Tests\Feature;

use App\Models\Employe;
use App\Models\Entreprise;
use App\Models\Succursale;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('succursales');
        Schema::dropIfExists('employes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('entreprises');

        parent::tearDown();
    }

    public function test_user_creation_requires_employee_when_a_succursale_is_active(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $succursale = Succursale::create([
            'entreprise_id' => $superAdmin->entreprise_id,
            'nom' => 'Goma',
            'adresse' => 'Goma',
            'manager_user_id' => null,
            'active' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['succursale_id' => $succursale->id])
            ->from('/users')
            ->post('/users', [
                'name' => 'Nouvel utilisateur',
                'email' => 'nouveau@example.com',
                'role' => 'user',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'admin_password' => 'password',
            ]);

        $response->assertRedirect('/users');
        $response->assertSessionHasErrors('employe_id');
        $this->assertDatabaseMissing('users', [
            'email' => 'nouveau@example.com',
        ]);
    }

    public function test_user_creation_in_active_succursale_assigns_unassigned_employee_to_that_succursale(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $succursale = Succursale::create([
            'entreprise_id' => $superAdmin->entreprise_id,
            'nom' => 'Lubumbashi',
            'adresse' => 'Lubumbashi',
            'manager_user_id' => null,
            'active' => true,
        ]);
        $employe = Employe::create([
            'entreprise_id' => $superAdmin->entreprise_id,
            'succursale_id' => null,
            'nom' => 'Agent PrimeGest',
            'email' => 'agent@example.com',
            'telephone' => '0990001111',
            'poste' => 'Caissier',
            'salaire_base' => 500000,
            'statut' => 'actif',
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['succursale_id' => $succursale->id])
            ->post('/users', [
                'name' => 'Compte Agent',
                'email' => 'ignore@example.com',
                'role' => 'user',
                'employe_id' => $employe->id,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'admin_password' => 'password',
            ]);

        $response->assertRedirect();

        $employe->refresh();

        $this->assertSame($succursale->id, $employe->succursale_id);
        $this->assertDatabaseHas('users', [
            'name' => 'Compte Agent',
            'email' => 'agent@example.com',
            'role' => 'user',
            'entreprise_id' => $superAdmin->entreprise_id,
            'employe_id' => $employe->id,
        ]);
    }

    public function test_central_admin_can_create_user_with_super_admin_password(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = User::factory()->create([
            'email' => 'central-admin@example.com',
            'password' => Hash::make('adminpass'),
            'role' => 'admin',
            'entreprise_id' => $superAdmin->entreprise_id,
        ]);

        $response = $this->actingAs($admin)
            ->from('/users')
            ->post('/users', [
                'name' => 'Utilisateur Central',
                'email' => 'central-user@example.com',
                'role' => 'user',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'admin_password' => 'password',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'Utilisateur Central',
            'email' => 'central-user@example.com',
            'role' => 'user',
            'entreprise_id' => $superAdmin->entreprise_id,
        ]);
    }

    private function createSuperAdmin(): User
    {
        $entreprise = Entreprise::create([
            'name' => 'PrimeGest Test',
            'email' => 'entreprise@example.com',
            'phone' => '0990000000',
            'address' => 'Lubumbashi',
        ]);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
        ]);

        $entreprise->update(['user_id' => $user->id]);

        return $user;
    }

    private function createTestSchema(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('plan')->nullable();
            $table->timestamp('plan_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->boolean('manager')->default(false);
            $table->unsignedBigInteger('entreprise_id')->nullable();
            $table->unsignedBigInteger('employe_id')->nullable();
            $table->json('access_pages')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('succursale_id')->nullable();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('poste')->nullable();
            $table->decimal('salaire_base', 12, 2)->default(0);
            $table->date('date_embauche')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
        });

        Schema::create('succursales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('nom');
            $table->string('adresse')->nullable();
            $table->unsignedBigInteger('manager_user_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
}
