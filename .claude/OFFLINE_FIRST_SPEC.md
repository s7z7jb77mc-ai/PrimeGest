# PRIMEGEST — Spécification Technique Offline-First
# Document destiné à Claude Code pour l'implémentation
# Version : 1.0 — Stack : Laravel 11 + Vue.js 3 + SQLite + Tauri 2

---

## RÉSUMÉ EXÉCUTIF

PrimeGest doit fonctionner 100% sans internet. Toutes les opérations
s'écrivent en SQLite local d'abord. Un agent (SyncWorker) envoie les
données au cloud MySQL quand internet est disponible.

Règle absolue : l'utilisateur ne doit JAMAIS attendre le réseau.

---

## ARCHITECTURE GLOBALE

```
[Vue.js UI] → [Laravel local :8888] → [SQLite local]
                                             ↓
                                      [sync_queue]
                                             ↓
                                      [SyncWorker]
                                             ↓ (si internet)
                              [API Cloud Laravel] → [MySQL cloud]
```

---

## PARTIE 1 — BASE DE DONNÉES SQLite LOCAL

### 1.1 Configuration Laravel

```php
// config/database.php
'default' => env('DB_CONNECTION', 'sqlite'),

'connections' => [
    'sqlite' => [
        'driver'   => 'sqlite',
        'database' => storage_path('app/primegest.db'),
        'foreign_key_constraints' => true,
    ],
    'mysql' => [
        // connexion cloud — utilisée uniquement par SyncWorker
        'driver'   => 'mysql',
        'host'     => env('CLOUD_DB_HOST'),
        'database' => env('CLOUD_DB_DATABASE'),
        'username' => env('CLOUD_DB_USERNAME'),
        'password' => env('CLOUD_DB_PASSWORD'),
    ],
],
```

```env
# .env local
DB_CONNECTION=sqlite
CLOUD_DB_HOST=primegest.app
CLOUD_DB_DATABASE=primegest_prod
CLOUD_DB_USERNAME=primegest_user
CLOUD_DB_PASSWORD=Byabuze21.03Darcy
```

### 1.2 Règle UUID — OBLIGATOIRE sur toutes les tables

Chaque table doit avoir DEUX identifiants :
- `id` : BIGINT auto-increment (clé primaire locale SQLite)
- `uuid` : VARCHAR(36) unique global (généré côté client)

L'uuid est l'identifiant utilisé pour la synchronisation cloud.
L'id local n'est JAMAIS envoyé au cloud.

```php
// Trait à appliquer sur tous les modèles
// app/Traits/HasUuid.php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
```

### 1.3 Migration type — à appliquer sur TOUTES les tables

```php
// Exemple avec la table ventes
// Même pattern pour : produits, clients, stocks, caisse,
// employes, fournisseurs, branches, transfers, dettes, etc.

Schema::create('mouvement', function (Blueprint $table) {
    $table->id();                                    // PK locale
    $table->unsignedBigInteger('entreprise_id');
    $table->uuid('uuid')->unique();   // ID global sync
    $table->unsignedBigInteger('succursale_id')->nullable(); // succursales
    $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
    $table->foreignId('produit_id')->constrained()->onDelete('cascade');
    $table->string('nom_produit');
    $table->string('type', 20);
    $table->integer('quantite');
    $table->decimal('prix_unitaire', 10, 2)->nullable();
    $table->decimal('prix_total', 10, 2)->nullable();
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->text('commentaire')->nullable();
    $table->timestamp('date')->useCurrent();
    $table->timestamps();


   
    // Champ de synchronisation
    $table->boolean('synced')->default(false);       // false = pas encore envoyé au cloud
    $table->string('device_id')->nullable();         // ID du poste qui a créé l'enregistrement
});
```

---

## PARTIE 2 — TABLE sync_queue

C'est le journal de toutes les opérations à synchroniser.
Chaque écriture (INSERT/UPDATE/DELETE) génère une ligne ici.

### 2.1 Migration

```php
Schema::create('sync_queue', function (Blueprint $table) {
    $table->id();
    $table->string('device_id');           // ID unique du poste client
    $table->string('table_name');          // ex: 'ventes', 'produits', 'stocks'
    $table->string('record_uuid');         // UUID de l'enregistrement concerné
    $table->string('succursale_uuid')->nullable(); // UUID de la succursale (si applicable)
     $table->string('entreprise_uuid')->nullable();
    $table->enum('operation', ['insert', 'update', 'delete']);
    $table->json('payload');               // données complètes sérialisées
    $table->string('checksum');            // SHA256 du payload pour vérifier intégrité
    $table->enum('status', ['pending', 'syncing', 'done', 'conflict'])
          ->default('pending');
    $table->unsignedTinyInteger('attempts')->default(0); // nb de tentatives
    $table->string('error_message')->nullable();         // message d'erreur si échec
    $table->timestamp('synced_at')->nullable();          // date de sync réussie
    $table->timestamp('created_at');                     // date de l'opération locale
});
```

### 2.2 États d'une entrée sync_queue

```
pending   → opération enregistrée, en attente d'internet
syncing   → en cours d'envoi (évite les doubles envois)
done      → synchronisée avec succès, peut être archivée
conflict  → conflit détecté, nécessite résolution
```

---

## PARTIE 3 — OBSERVERS Laravel (alimentation automatique sync_queue)

Les Observers écoutent chaque écriture sur les modèles et créent
automatiquement une entrée dans sync_queue. L'utilisateur ne voit rien.

### 3.1 Trait SyncObservable — à appliquer sur tous les modèles

```php
// app/Traits/SyncObservable.php

namespace App\Traits;

use App\Models\SyncQueue;
use Illuminate\Support\Facades\Hash;

trait SyncObservable
{
    protected static function bootSyncObservable(): void
    {
        // Après chaque création
        static::created(function ($model) {
            static::addToSyncQueue($model, 'insert');
        });

        // Après chaque mise à jour
        static::updated(function ($model) {
            // Ne pas re-synchroniser si c'est le champ 'synced' qui change
            if ($model->wasChanged(['synced'])) return;
            static::addToSyncQueue($model, 'update');
        });

        // Après chaque suppression
        static::deleted(function ($model) {
            static::addToSyncQueue($model, 'delete');
        });
    }

    protected static function addToSyncQueue($model, string $operation): void
    {
        $payload = $operation === 'delete'
            ? ['uuid' => $model->uuid]           // suppression : juste l'uuid suffit
            : $model->toArray();                  // insert/update : données complètes

        // Retirer l'id local du payload (ne jamais l'envoyer au cloud)
        unset($payload['id']);

        SyncQueue::create([
            'device_id'   => config('app.device_id'), // ID unique du poste
            'table_name'  => $model->getTable(),
            'record_uuid' => $model->uuid,
            'succursale_uuid' => $model->succursale?->uuid ?? null,
            'ebtreprise_uuide' => $e->entreprise_uuid,
            'operation'   => $operation,
            'payload'     => $payload,
            'checksum'    => hash('sha256', json_encode($payload)),
            'status'      => 'pending',
        ]);
    }
}
```

### 3.2 Appliquer les traits sur chaque modèle

```php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class MouvementStock extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'produit_id',
        'nom_produit',
        'type',
        'quantite',
        'prix_unitaire',
        'prix_total',
        'user_id',
        'commentaire',
        'payment_type',
        'date',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}


// Même chose pour : Produit, Client, Stock, Caisse,
// Employe, Fournisseur, Branch, Transfer, Dette, Journal, etc.
```

---

## PARTIE 4 — SYNC WORKER (cœur du système offline-first)

Le SyncWorker est un job Laravel qui tourne en arrière-plan.
Il vérifie la connexion toutes les 30 secondes et envoie les données.

### 4.1 Le Job SyncWorker

```php
// app/Jobs/SyncWorker.php

namespace App\Jobs;

use App\Models\SyncQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWorker implements ShouldQueue
{
    use Queueable;

    private const CLOUD_API_URL  = 'https://primegest.app/api/v1/sync';
    private const BATCH_SIZE     = 50;    // nb d'opérations par envoi
    private const PING_TIMEOUT   = 5;     // secondes avant de déclarer offline
    private const MAX_ATTEMPTS   = 5;     // tentatives max avant de marquer conflict

    public function handle(): void
    {
        // ÉTAPE 1 : Vérifier si internet est disponible
        if (!$this->isOnline()) {
            Log::info('SyncWorker: hors ligne, sync reportée');
            $this->reschedule();
            return;
        }

        // ÉTAPE 2 : Récupérer les entrées pending en batch
        $entries = SyncQueue::where('status', 'pending')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('created_at', 'asc')  // ordre chronologique OBLIGATOIRE
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($entries->isEmpty()) {
            $this->reschedule();
            return;
        }

        // ÉTAPE 3 : Marquer comme "syncing" (évite les doubles envois)
        $ids = $entries->pluck('id');
        SyncQueue::whereIn('id', $ids)->update(['status' => 'syncing']);

        // ÉTAPE 4 : Envoyer le batch au cloud
        try {
            $response = Http::withToken($this->getApiToken())
                ->timeout(30)
                ->post(self::CLOUD_API_URL, [
                    'device_id' => config('app.device_id'),
                    'batch'     => $entries->map(fn($e) => [
                        'table_name'  => $e->table_name,
                        'record_uuid' => $e->record_uuid,
                        'succursale_uuid' => $e->succursale_uuid,
                        'ebtreprise_uuide' => $e->entreprise_uuid,
                        'operation'   => $e->operation,
                        'payload'     => $e->payload,
                        'checksum'    => $e->checksum,
                        'local_time'  => $e->created_at->toIso8601String(),
                    ])->toArray(),
                ]);

            // ÉTAPE 5 : Traiter la réponse du cloud
            if ($response->successful()) {
                $results = $response->json('results', []);
                $this->processResults($entries, $results);
            } else {
                // Le cloud a retourné une erreur — remettre en pending
                SyncQueue::whereIn('id', $ids)->update([
                    'status'        => 'pending',
                    'attempts'      => \DB::raw('attempts + 1'),
                    'error_message' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            // Erreur réseau — remettre en pending pour retry
            SyncQueue::whereIn('id', $ids)->update([
                'status'        => 'pending',
                'attempts'      => \DB::raw('attempts + 1'),
                'error_message' => $e->getMessage(),
            ]);
        }

        $this->reschedule();
    }

    private function processResults(iterable $entries, array $results): void
    {
        foreach ($results as $result) {
            $entry = $entries->firstWhere('record_uuid', $result['record_uuid']);
            if (!$entry) continue;

            match ($result['status']) {
                'done'     => $entry->update([
                                  'status'    => 'done',
                                  'synced_at' => now(),
                              ]),
                'conflict' => $entry->update([
                                  'status'        => 'conflict',
                                  'error_message' => $result['message'] ?? 'Conflit détecté',
                              ]),
                default    => $entry->update([
                                  'status'        => 'pending',
                                  'attempts'      => $entry->attempts + 1,
                              ]),
            };
        }
    }

    private function isOnline(): bool
    {
        try {
            $response = Http::timeout(self::PING_TIMEOUT)
                ->get('https://primegest.app/api/health');
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    private function reschedule(): void
    {
        // Re-planifier dans 30 secondes
        self::dispatch()->delay(now()->addSeconds(30));
    }

    private function getApiToken(): string
    {
        // Token stocké localement après authentification
        return config('app.sync_token', '');
    }
}
```

### 4.2 Démarrer le SyncWorker au lancement de l'app

```php
// app/Providers/AppServiceProvider.php

use App\Jobs\SyncWorker;

public function boot(): void
{
    // Démarrer le worker en arrière-plan au lancement
    if (config('app.env') !== 'testing') {
        SyncWorker::dispatch()->delay(now()->addSeconds(10));
    }
}
```

---

## PARTIE 5 — ENDPOINT CLOUD (réception des données)

L'API cloud reçoit les batches du SyncWorker et les applique à MySQL.

### 5.1 Route

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/sync',    [SyncController::class, 'receive']);
    Route::get('/health',      fn() => response()->json(['status' => 'ok']));
});
```

### 5.2 SyncController côté cloud

```php
// app/Http/Controllers/Api/SyncController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        $batch   = $request->input('batch', []);
        $results = [];

        foreach ($batch as $entry) {
            try {
                $result = $this->processEntry($entry);
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'record_uuid' => $entry['record_uuid'],
                    'status'      => 'error',
                    'message'     => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function processEntry(array $entry): array
    {
        $table      = $entry['table_name'];
        $uuid       = $entry['record_uuid'];
        $operation  = $entry['operation'];
        $payload    = $entry['payload'];
        $localTime  = $entry['local_time'];

        // Vérifier l'intégrité du payload
        $checksum = hash('sha256', json_encode($payload));
        if ($checksum !== $entry['checksum']) {
            return ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'Checksum invalide'];
        }

        return DB::transaction(function () use ($table, $uuid, $operation, $payload, $localTime) {
            return match ($operation) {
                'insert' => $this->handleInsert($table, $uuid, $payload),
                'update' => $this->handleUpdate($table, $uuid, $payload, $localTime),
                'delete' => $this->handleDelete($table, $uuid),
                default  => ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'Opération inconnue'],
            };
        });
    }

    private function handleInsert(string $table, string $uuid, array $payload): array
    {
        // Si l'enregistrement existe déjà (insert dupliqué) → ignorer
        $existing = DB::table($table)->where('uuid', $uuid)->first();
        if ($existing) {
            return ['record_uuid' => $uuid, 'status' => 'done', 'message' => 'Déjà présent'];
        }

        // Retirer l'id local avant d'insérer dans MySQL
        unset($payload['id']);
        DB::table($table)->insert($payload);

        return ['record_uuid' => $uuid, 'status' => 'done'];
    }

    private function handleUpdate(string $table, string $uuid, array $payload, string $localTime): array
    {
        $existing = DB::table($table)->where('uuid', $uuid)->first();

        if (!$existing) {
            // L'enregistrement n'existe pas encore → insérer
            unset($payload['id']);
            DB::table($table)->insert($payload);
            return ['record_uuid' => $uuid, 'status' => 'done'];
        }

        // RÉSOLUTION DE CONFLIT : Last-Write-Wins par timestamp
        // Si le cloud a un updated_at plus récent → conflit
        $cloudUpdatedAt = $existing->updated_at ?? '1970-01-01';
        if ($cloudUpdatedAt > $localTime) {
            // Le cloud est plus récent → archiver la version locale et garder le cloud
            DB::table('conflict_log')->insert([
                'table_name'   => $table,
                'record_uuid'  => $uuid,
                'local_payload'=> json_encode($payload),
                'cloud_payload'=> json_encode((array) $existing),
                'created_at'   => now(),
            ]);
            return [
                'record_uuid' => $uuid,
                'status'      => 'conflict',
                'message'     => 'Version cloud plus récente — données locales archivées dans conflict_log',
            ];
        }

        // La version locale est plus récente → mettre à jour le cloud
        unset($payload['id']);
        DB::table($table)->where('uuid', $uuid)->update($payload);

        return ['record_uuid' => $uuid, 'status' => 'done'];
    }

    private function handleDelete(string $table, string $uuid): array
    {
        DB::table($table)->where('uuid', $uuid)->delete();
        return ['record_uuid' => $uuid, 'status' => 'done'];
    }
}
```

---

## PARTIE 6 — STATUT DE SYNCHRONISATION (UI Vue.js)

L'utilisateur voit l'état de la sync en permanence dans l'interface.

### 6.1 Store Pinia — syncStore

```javascript
// stores/sync.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useSyncStore = defineStore('sync', () => {

    const pendingCount = ref(0)
    const isOnline     = ref(navigator.onLine)
    const isSyncing    = ref(false)
    const lastSyncAt   = ref(null)

    // Statut calculé
    const status = computed(() => {
        if (!isOnline.value)      return 'offline'
        if (isSyncing.value)      return 'syncing'
        if (pendingCount.value > 0) return 'pending'
        return 'synced'
    })

    // Label affiché à l'utilisateur
    const statusLabel = computed(() => ({
        offline:  '🔴 Hors ligne',
        syncing:  '🔄 Synchronisation...',
        pending:  `🟡 En attente (${pendingCount.value})`,
        synced:   '🟢 Synchronisé',
    }[status.value]))

    // Rafraîchir le compteur depuis l'API locale
    async function refresh() {
        try {
            const { data } = await axios.get('/api/local/sync-status')
            pendingCount.value = data.pending_count
            isSyncing.value    = data.is_syncing
            lastSyncAt.value   = data.last_sync_at
        } catch (e) {
            // L'API locale ne répond pas — mode offline
            isOnline.value = false
        }
    }

    // Écouter les changements de connexion
    window.addEventListener('online',  () => { isOnline.value = true;  refresh() })
    window.addEventListener('offline', () => { isOnline.value = false })

    // Rafraîchir toutes les 30 secondes
    setInterval(refresh, 30_000)
    refresh()

    return { status, statusLabel, pendingCount, isOnline, isSyncing, lastSyncAt, refresh }
})
```

### 6.2 Composant Vue.js SyncIndicator

```vue
<!-- components/SyncIndicator.vue -->
<template>
  <div class="sync-indicator" :class="`sync--${sync.status}`">
    <span class="sync-dot"></span>
    <span class="sync-label">{{ sync.statusLabel }}</span>

    <!-- Tooltip avec détails -->
    <div class="sync-tooltip" v-if="sync.pendingCount > 0">
      {{ sync.pendingCount }} opération(s) en attente de synchronisation
      <br>
      Elles seront envoyées automatiquement dès que la connexion revient.
    </div>
  </div>
</template>

<script setup>
import { useSyncStore } from '@/stores/sync'
const sync = useSyncStore()
</script>
```

---

## PARTIE 7 — DEVICE ID (identifiant unique du poste)

Chaque installation de PrimeGest sur un poste a un ID unique.
Cet ID est utilisé dans sync_queue pour identifier l'origine des données.

```php
// app/Console/Commands/GenerateDeviceId.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateDeviceId extends Command
{
    protected $signature   = 'primegest:device-id';
    protected $description = 'Générer un identifiant unique pour ce poste';

    public function handle(): void
    {
        $deviceIdFile = storage_path('app/device_id');

        if (!file_exists($deviceIdFile)) {
            $deviceId = 'device_' . Str::uuid();
            file_put_contents($deviceIdFile, $deviceId);
            $this->info("Device ID généré : {$deviceId}");
        } else {
            $this->info("Device ID existant : " . file_get_contents($deviceIdFile));
        }
    }
}

// Dans config/app.php
'device_id' => file_exists(storage_path('app/device_id'))
    ? file_get_contents(storage_path('app/device_id'))
    : 'unknown_device',
```

---

## PARTIE 8 — ORDRE D'IMPLÉMENTATION POUR CLAUDE CODE

Implémentez dans cet ordre exact. Ne pas sauter d'étape.

```
ÉTAPE 1 : Configurer SQLite dans config/database.php
ÉTAPE 2 : Créer le trait HasUuid + l'appliquer sur tous les modèles
ÉTAPE 3 : Créer le trait SyncObservable + l'appliquer sur tous les modèles
ÉTAPE 4 : Créer la migration sync_queue + conflict_log
ÉTAPE 5 : Créer le modèle SyncQueue
ÉTAPE 6 : Créer le Job SyncWorker
ÉTAPE 7 : Créer l'endpoint cloud POST /api/v1/sync (SyncController)
ÉTAPE 8 : Créer l'endpoint GET /api/health
ÉTAPE 9 : Créer l'endpoint local GET /api/local/sync-status
ÉTAPE 10: Créer le store Pinia syncStore
ÉTAPE 11: Créer le composant Vue.js SyncIndicator
ÉTAPE 12: Démarrer le SyncWorker dans AppServiceProvider
ÉTAPE 13: Créer la commande primegest:device-id
ÉTAPE 14: Tester offline : couper internet → faire des ventes → reconnecter → vérifier sync
```

---

## PARTIE 9 — TESTS À EFFECTUER

```bash
# Test 1 : Vérifier que SQLite fonctionne
php artisan migrate
php artisan tinker
>>> App\Models\Vente::create([...]) # doit générer uuid automatiquement

# Test 2 : Vérifier que sync_queue se remplit
>>> App\Models\SyncQueue::count() # doit être > 0 après création

# Test 3 : Tester le SyncWorker manuellement
php artisan tinker
>>> dispatch(new App\Jobs\SyncWorker())

# Test 4 : Vérifier le statut
>>> App\Models\SyncQueue::where('status', 'done')->count()

# Test 5 : Test complet offline
# 1. Couper internet
# 2. Créer des ventes dans l'app
# 3. Vérifier SyncQueue::where('status','pending')->count() > 0
# 4. Reconnecter internet
# 5. Vérifier SyncQueue::where('status','done')->count() augmente
```

---

## NOTES FINALES POUR CLAUDE CODE

1. Le modèle SyncQueue lui-même NE DOIT PAS utiliser le trait SyncObservable
   (sinon boucle infinie)

2. Les migrations SQLite et MySQL doivent être IDENTIQUES en structure

3. Toujours utiliser updated_at pour la résolution de conflits

4. Le payload dans sync_queue ne doit JAMAIS contenir le champ 'id' local

5. Les tables suivantes doivent toutes avoir uuid + succursale_id + synced + device_id :
   - mouvement_stoks, factures, produits, clients, fournisseurs
   - caisse, employes, archive, creances, stocks, transfers, dettes, journals, factures
   - fiches_paie, taches, messages

6. La table sync_queue et conflict_log sont LOCAL uniquement — pas synchronisées

"Lis le fichier OFFLINE_FIRST_SPEC.md et implémente tout dans l'ordre exact indiqué en Partie 8, étape par étape. Commence par l'Étape 1."

