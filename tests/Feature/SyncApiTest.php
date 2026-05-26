<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entreprise;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Entreprise $entreprise;
    private string $deviceId = 'test-device-abc123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::factory()->create(['plan' => 'premium']);

        $this->user = User::factory()->create([
            'role'          => 'super_admin',
            'entreprise_id' => $this->entreprise->id,
            'password'      => Hash::make('password'),
        ]);

        $this->entreprise->update(['user_id' => $this->user->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — authentification
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_non_authentifie_retourne_401(): void
    {
        $this->postJson('/api/sync/push', [
            'device_id'  => $this->deviceId,
            'operations' => [],
        ])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — validation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_sans_device_id_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'operations' => [$this->makeOp('clients')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['device_id']);
    }

    public function test_push_sans_operations_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id' => $this->deviceId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operations']);
    }

    public function test_push_tableau_vide_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [],
            ])
            ->assertStatus(422);
    }

    public function test_push_operation_invalide_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    [
                        'table_name'          => 'clients',
                        'record_id'           => 'pas-un-uuid',
                        'operation'           => 'create',
                        'payload'             => [],
                        'client_sync_version' => 0,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operations.0.record_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — create
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_create_client_cree_en_base(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    [
                        'table_name'          => 'clients',
                        'record_id'           => $uuid,
                        'operation'           => 'create',
                        'payload'             => [
                            'nom_client'        => 'Jean Test',
                            'numero_telephone'  => '0999999999',
                            'adresse'           => 'Kinshasa',
                        ],
                        'client_sync_version' => 0,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('clients', [
            'uuid'           => $uuid,
            'entreprise_id'  => $this->entreprise->id,
            'nom_client'     => 'Jean Test',
        ]);
    }

    public function test_push_create_preserve_uuid_client(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [$this->makeOp('clients', $uuid, 'create', ['nom_client' => 'Alice', 'numero_telephone' => '0999999998'])],
            ])
            ->assertOk();

        $client = Client::withoutGlobalScopes()->where('uuid', $uuid)->first();
        $this->assertNotNull($client, "Le client avec l'UUID $uuid devrait exister");
        $this->assertSame($uuid, $client->uuid);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — update (upsert LWW)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_update_client_met_a_jour_en_base(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        // Créer le client d'abord
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'              => $uuid,
            'entreprise_id'     => $this->entreprise->id,
            'succursale_id'     => null,
            'nom_client'        => 'Ancien nom',
            'numero_telephone'  => '0999000000',
        ]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    $this->makeOp('clients', $uuid, 'update', ['nom_client' => 'Nouveau nom']),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('clients', [
            'uuid'       => $uuid,
            'nom_client' => 'Nouveau nom',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — delete
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_delete_client_le_supprime(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $uuid,
            'entreprise_id'    => $this->entreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'À supprimer',
            'numero_telephone' => '0999000001',
        ]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    $this->makeOp('clients', $uuid, 'delete', []),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseMissing('clients', [
            'uuid'       => $uuid,
            'deleted_at' => null,
        ]);
    }

    public function test_push_delete_record_inexistant_retourne_not_found(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    $this->makeOp('clients', $uuid, 'delete', []),
                ],
            ])
            ->assertOk();

        $this->assertSame('not_found', $response->json('results.0.status'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — table inconnue
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_table_inconnue_retourne_ignored(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    $this->makeOp('table_inexistante', null, 'create', ['nom' => 'test']),
                ],
            ])
            ->assertOk();

        $this->assertSame('ignored', $response->json('results.0.status'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — isolation entreprise
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_ne_peut_pas_modifier_client_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $uuid            = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $uuid,
            'entreprise_id'    => $autreEntreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'Client autre entreprise',
            'numero_telephone' => '0999000002',
        ]));

        // L'upsert est forcé sur l'entreprise de l'user connecté,
        // donc le delete sur l'autre entreprise retourne not_found
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    $this->makeOp('clients', $uuid, 'delete', []),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'not_found');

        // Le client de l'autre entreprise reste intact
        $this->assertDatabaseHas('clients', [
            'uuid'       => $uuid,
            'nom_client' => 'Client autre entreprise',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — batch 100 max
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_plus_de_100_operations_retourne_422(): void
    {
        $ops = array_fill(0, 101, $this->makeOp('clients'));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => $ops,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operations']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PULL — authentification & validation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pull_non_authentifie_retourne_401(): void
    {
        $this->getJson('/api/sync/pull?since=0&device_id='.$this->deviceId)
            ->assertStatus(401);
    }

    public function test_pull_sans_since_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/pull?device_id='.$this->deviceId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['since']);
    }

    public function test_pull_sans_device_id_retourne_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/pull?since=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['device_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PULL — delta
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pull_since_zero_retourne_tous_les_clients(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $uuid,
            'entreprise_id'    => $this->entreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'Client pull test',
            'numero_telephone' => '0999000004',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/pull?since=0&device_id='.$this->deviceId)
            ->assertOk()
            ->assertJsonStructure(['delta', 'server_ts']);

        $clients = $response->json('delta.clients');
        $uuids   = array_column($clients, 'uuid');

        $this->assertContains($uuid, $uuids);
    }

    public function test_pull_retourne_seulement_records_modifies_apres_since(): void
    {
        // Client créé avant since
        $ancienUuid = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $ancienUuid,
            'entreprise_id'    => $this->entreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'Ancien client',
            'numero_telephone' => '0999000005',
        ]));

        // Attendre 1 s pour que updated_at de l'ancien soit < since (précision subseconde)
        sleep(1);

        // since = maintenant (après l'ancien, avant le nouveau)
        $since = now()->timestamp;
        sleep(1);

        // Client créé après since
        $nouveauUuid = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $nouveauUuid,
            'entreprise_id'    => $this->entreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'Nouveau client',
            'numero_telephone' => '0999000006',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since={$since}&device_id={$this->deviceId}")
            ->assertOk();

        $uuids = array_column($response->json('delta.clients') ?? [], 'uuid');

        $this->assertContains($nouveauUuid, $uuids);
        $this->assertNotContains($ancienUuid, $uuids);
    }

    public function test_pull_nexpose_pas_clients_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $uuidAutre       = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => Client::create([
            'uuid'             => $uuidAutre,
            'entreprise_id'    => $autreEntreprise->id,
            'succursale_id'    => null,
            'nom_client'       => 'Client confidentiel',
            'numero_telephone' => '0999000003',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/pull?since=0&device_id='.$this->deviceId)
            ->assertOk();

        $uuids = array_column($response->json('delta.clients') ?? [], 'uuid');
        $this->assertNotContains($uuidAutre, $uuids);
    }

    public function test_pull_retourne_structure_delta_complete(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/pull?since=0&device_id='.$this->deviceId)
            ->assertOk();

        foreach (['clients', 'produits', 'caisses', 'journals', 'mouvement_stocks'] as $table) {
            $this->assertArrayHasKey($table, $response->json('delta'));
        }
        $this->assertIsInt($response->json('server_ts'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sync_status_retourne_online_true(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sync/status')
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonStructure(['online', 'server_ts', 'plan']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function makeOp(
        string $table,
        ?string $uuid = null,
        string $operation = 'create',
        array $payload = [],
    ): array {
        return [
            'table_name'          => $table,
            'record_id'           => $uuid ?? (string) \Illuminate\Support\Str::uuid(),
            'operation'           => $operation,
            'payload'             => $payload ?: ['nom_client' => 'Test '.rand(1, 9999), 'numero_telephone' => '0999000000'],
            'client_sync_version' => 0,
        ];
    }
}
