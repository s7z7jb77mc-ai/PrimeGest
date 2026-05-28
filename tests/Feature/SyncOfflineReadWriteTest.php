<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Caisse;
use App\Models\Employe;
use App\Models\Entreprise;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests complémentaires offline-first : push/pull sur les tables
 * non couvertes par SyncApiTest (fournisseurs, employes, caisses).
 *
 * Vérifie les opérations de lecture (pull delta) et d'écriture (push)
 * pour les versions desktop et mobile, qui utilisent toutes les deux
 * les endpoints /api/sync/push et /api/sync/pull.
 */
class SyncOfflineReadWriteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Entreprise $entreprise;
    private string $deviceId = 'sync-rw-device-001';

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
    // PUSH — fournisseurs
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_create_fournisseur_cree_en_base(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'nom_entreprise_fournisseur' => 'Bio SARL',
                        'adresse'                   => 'Goma, Nord-Kivu',
                        'reduction_pourcentage'     => 10,
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('fournisseurs', [
            'uuid'                       => $uuid,
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Bio SARL',
        ]);
    }

    public function test_push_update_fournisseur_met_a_jour_en_base(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Avant update',
            'adresse'                    => 'Kinshasa',
        ]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => $uuid,
                    'operation'           => 'update',
                    'payload'             => [
                        'nom_entreprise_fournisseur' => 'Après update',
                        'adresse'                   => 'Lubumbashi',
                    ],
                    'client_sync_version' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('fournisseurs', [
            'uuid'                       => $uuid,
            'nom_entreprise_fournisseur' => 'Après update',
        ]);
    }

    public function test_push_delete_fournisseur_le_supprime(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'À supprimer',
            'adresse'                    => 'Bukavu',
        ]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => $uuid,
                    'operation'           => 'delete',
                    'payload'             => ['_delete' => true], // payload non vide requis par le validateur
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseMissing('fournisseurs', ['uuid' => $uuid]);
    }

    public function test_push_delete_fournisseur_autre_entreprise_retourne_not_found(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $autreEntreprise->id,
            'nom_entreprise_fournisseur' => 'Adverse',
            'adresse'                    => 'Goma',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => $uuid,
                    'operation'           => 'delete',
                    'payload'             => ['_delete' => true],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk();

        $this->assertSame('not_found', $response->json('results.0.status'));
        // Le fournisseur de l'autre entreprise reste intact
        $this->assertDatabaseHas('fournisseurs', ['uuid' => $uuid]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — employes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_create_employe_cree_en_base(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'employes',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'nom'          => 'Kabila Jean',
                        'prenom'       => 'Pierre',
                        'poste'        => 'Caissier',
                        'salaire_base' => 450000,
                        'email'        => 'kabila.jean@test.com',
                        'statut'       => 'actif',
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('employes', [
            'uuid'          => $uuid,
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Kabila Jean',
        ]);
    }

    public function test_push_update_employe_met_a_jour_en_base(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Employe::create([
            'uuid'          => $uuid,
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Avant',
            'poste'         => 'Vendeur',
            'email'         => 'avant@test.com',
            'salaire_base'  => 300000,
            'statut'        => 'actif',
        ]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'employes',
                    'record_id'           => $uuid,
                    'operation'           => 'update',
                    'payload'             => [
                        'nom'          => 'Après',
                        'poste'        => 'Manager',
                        'email'        => 'apres@test.com',
                        'salaire_base' => 600000,
                        'statut'       => 'actif',
                    ],
                    'client_sync_version' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('employes', [
            'uuid'    => $uuid,
            'nom'     => 'Après',
            'poste'   => 'Manager',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — caisses
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_create_caisse_cree_en_base(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'caisses',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'date_operation' => '2026-05-27',
                        'description'    => 'Vente journalière',
                        'entree'         => 75000,
                        'sortie'         => 0,
                        'solde'          => 75000,
                        'type_operation' => 'vente',
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertDatabaseHas('caisses', [
            'uuid'          => $uuid,
            'entreprise_id' => $this->entreprise->id,
            'description'   => 'Vente journalière',
            'entree'        => 75000,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PULL — tables complémentaires
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pull_retourne_delta_fournisseurs(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Pull Fourn SARL',
            'adresse'                    => 'Goma',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk()
            ->assertJsonStructure(['delta', 'server_ts']);

        $uuids = array_column($response->json('delta.fournisseurs') ?? [], 'uuid');
        $this->assertContains($uuid, $uuids, 'Le fournisseur doit apparaître dans le delta pull');
    }

    public function test_pull_retourne_delta_employes(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Employe::create([
            'uuid'          => $uuid,
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Delta Employe',
            'poste'         => 'Caissier',
            'email'         => 'delta.employe@test.com',
            'salaire_base'  => 400000,
            'statut'        => 'actif',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $uuids = array_column($response->json('delta.employes') ?? [], 'uuid');
        $this->assertContains($uuid, $uuids, "L'employé doit apparaître dans le delta pull");
    }

    public function test_pull_retourne_delta_caisses(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Caisse::create([
            'uuid'           => $uuid,
            'entreprise_id'  => $this->entreprise->id,
            'description'    => 'Delta caisse',
            'date_operation' => now(),
            'entree'         => 20000,
            'sortie'         => 0,
            'solde'          => 20000,
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $uuids = array_column($response->json('delta.caisses') ?? [], 'uuid');
        $this->assertContains($uuid, $uuids, "L'opération caisse doit apparaître dans le delta pull");
    }

    public function test_pull_nexpose_pas_fournisseurs_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $autreEntreprise->id,
            'nom_entreprise_fournisseur' => 'Confidentiel SARL',
            'adresse'                    => 'Kinshasa',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $uuids = array_column($response->json('delta.fournisseurs') ?? [], 'uuid');
        $this->assertNotContains($uuid, $uuids, 'Les fournisseurs des autres entreprises ne doivent pas fuiter');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Round-trip push → pull
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_fournisseur_puis_pull_retourne_record(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'nom_entreprise_fournisseur' => 'Round Trip SARL',
                        'adresse'                   => 'Beni',
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $fournisseurs = $response->json('delta.fournisseurs');
        $found = collect($fournisseurs)->firstWhere('uuid', $uuid);

        $this->assertNotNull($found, 'Le fournisseur créé via push doit apparaître dans le pull');
        $this->assertSame('Round Trip SARL', $found['payload']['nom_entreprise_fournisseur']);
    }

    public function test_push_employe_puis_pull_retourne_record(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'employes',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'nom'          => 'Muamba Dieu',
                        'poste'        => 'Comptable',
                        'email'        => 'muamba.dieu@test.com',
                        'salaire_base' => 700000,
                        'statut'       => 'actif',
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $employes = $response->json('delta.employes');
        $found = collect($employes)->firstWhere('uuid', $uuid);

        $this->assertNotNull($found, "L'employé créé via push doit apparaître dans le pull");
        $this->assertSame('Muamba Dieu', $found['payload']['nom']);
    }

    public function test_push_caisse_puis_pull_retourne_record(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'caisses',
                    'record_id'           => $uuid,
                    'operation'           => 'create',
                    'payload'             => [
                        'date_operation' => '2026-05-27',
                        'description'    => 'Caisse round-trip',
                        'entree'         => 30000,
                        'sortie'         => 0,
                        'solde'          => 30000,
                    ],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $caisses = $response->json('delta.caisses');
        $found = collect($caisses)->firstWhere('uuid', $uuid);

        $this->assertNotNull($found, "L'opération caisse créée via push doit apparaître dans le pull");
        $this->assertSame('Caisse round-trip', $found['payload']['description']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — batch multi-tables (fournisseur + employe)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_batch_fournisseur_et_employe(): void
    {
        $fournisseurUuid = (string) Str::uuid();
        $employeUuid     = (string) Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [
                    [
                        'table_name'          => 'fournisseurs',
                        'record_id'           => $fournisseurUuid,
                        'operation'           => 'create',
                        'payload'             => [
                            'nom_entreprise_fournisseur' => 'Batch Fourn',
                            'adresse'                   => 'Goma',
                        ],
                        'client_sync_version' => 0,
                    ],
                    [
                        'table_name'          => 'employes',
                        'record_id'           => $employeUuid,
                        'operation'           => 'create',
                        'payload'             => [
                            'nom'          => 'Batch Employe',
                            'email'        => 'batch.employe@test.com',
                            'poste'        => 'Agent',
                            'salaire_base' => 350000,
                            'statut'       => 'actif',
                        ],
                        'client_sync_version' => 0,
                    ],
                ],
            ])
            ->assertOk();

        $results  = $response->json('results');
        $statuses = array_column($results, 'status');

        $this->assertCount(2, $results);
        $this->assertContains('synced', $statuses);
        $this->assertDatabaseHas('fournisseurs', ['uuid' => $fournisseurUuid]);
        $this->assertDatabaseHas('employes',     ['uuid' => $employeUuid]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUSH — structure de la réponse (server_ts + conflicts)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_push_fournisseur_response_contient_server_ts(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sync/push', [
                'device_id'  => $this->deviceId,
                'operations' => [[
                    'table_name'          => 'fournisseurs',
                    'record_id'           => (string) Str::uuid(),
                    'operation'           => 'create',
                    'payload'             => ['nom_entreprise_fournisseur' => 'Ts test', 'adresse' => 'X'],
                    'client_sync_version' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJsonStructure(['results', 'server_ts', 'conflicts']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PULL — structure item delta (payload sans clés internes)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pull_structure_item_delta_fournisseur(): void
    {
        $uuid = (string) Str::uuid();

        Model::unguarded(fn () => Fournisseur::create([
            'uuid'                       => $uuid,
            'entreprise_id'              => $this->entreprise->id,
            'nom_entreprise_fournisseur' => 'Structure test',
            'adresse'                    => 'Kinshasa',
        ]));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sync/pull?since=0&device_id={$this->deviceId}")
            ->assertOk();

        $fournisseurs = $response->json('delta.fournisseurs');
        $item = collect($fournisseurs)->firstWhere('uuid', $uuid);

        $this->assertNotNull($item);
        $this->assertArrayHasKey('uuid',          $item);
        $this->assertArrayHasKey('sync_version',  $item);
        $this->assertArrayHasKey('updated_at_ts', $item);
        $this->assertArrayHasKey('deleted_at',    $item);
        $this->assertArrayHasKey('payload',       $item);

        // Les clés internes ne doivent PAS être dans le payload
        $this->assertArrayNotHasKey('id',            $item['payload']);
        $this->assertArrayNotHasKey('uuid',          $item['payload']);
        $this->assertArrayNotHasKey('entreprise_id', $item['payload']);
    }
}
