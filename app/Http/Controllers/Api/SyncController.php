<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    // Tables métier autorisées — jamais users, entreprises, personal_access_tokens, etc.
    private const ALLOWED_TABLES = [
        'clients', 'fournisseurs', 'produits', 'caisses',
        'journals', 'mouvement_stocks', 'factures', 'employes',
        'succursales', 'transferts', 'bon_entrees', 'stocks', 'parametres',
        'fiches_paie', 'creances', 'dettes',
    ];

    /**
     * Flux officiel offline-first.
     * Reçoit un batch provenant de la file SQLite locale via SyncWorker.
     */
    public function receive(Request $request): JsonResponse
    {
        $batch = $request->input('batch', []);
        $results = [];
        $entrepriseId = $request->user()->entreprise_id;

        foreach ($batch as $entry) {
            try {
                $results[] = $this->processEntry($entry, $entrepriseId);
            } catch (\Exception $e) {
                $results[] = [
                    'record_uuid' => $entry['record_uuid'] ?? null,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function processEntry(array $entry, int $entrepriseId): array
    {
        $table = $entry['table_name'] ?? '';
        $uuid = $entry['record_uuid'] ?? null;
        $operation = $entry['operation'] ?? '';
        $payload = $entry['payload'] ?? [];
        $localTime = $entry['local_time'] ?? '1970-01-01';

        // Whitelist stricte — aucune table système ne peut être modifiée
        if (! in_array($table, self::ALLOWED_TABLES, true)) {
            return ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'Table non autorisée'];
        }

        if (! $uuid) {
            return ['record_uuid' => null, 'status' => 'error', 'message' => 'UUID manquant'];
        }

        // Vérifier que le payload ne tente pas d'écrire dans une autre entreprise
        if (isset($payload['entreprise_id']) && (int) $payload['entreprise_id'] !== $entrepriseId) {
            return ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'entreprise_id invalide'];
        }

        // Vérifier que succursale_id appartient bien à cette entreprise (anti cross-branch)
        if (isset($payload['succursale_id']) && $payload['succursale_id'] !== null) {
            $succursaleOk = DB::connection('mysql_cloud')->table('succursales')
                ->where('id', (int) $payload['succursale_id'])
                ->where('entreprise_id', $entrepriseId)
                ->exists();
            if (! $succursaleOk) {
                return ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'succursale_id invalide'];
            }
        }

        // Forcer l'entreprise_id de l'utilisateur authentifié
        $payload['entreprise_id'] = $entrepriseId;

        $checksum = hash('sha256', json_encode($entry['payload'] ?? []));
        if ($checksum !== ($entry['checksum'] ?? '')) {
            return ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'Checksum invalide'];
        }

        return DB::connection('mysql_cloud')->transaction(function () use ($table, $uuid, $operation, $payload, $localTime, $entrepriseId) {
            return match ($operation) {
                'insert' => $this->handleInsert($table, $uuid, $payload, $entrepriseId),
                'update' => $this->handleUpdate($table, $uuid, $payload, $localTime, $entrepriseId),
                'delete' => $this->handleDelete($table, $uuid, $entrepriseId),
                default => ['record_uuid' => $uuid, 'status' => 'error', 'message' => 'Opération inconnue'],
            };
        });
    }

    private function handleInsert(string $table, string $uuid, array $payload, int $entrepriseId): array
    {
        $existing = DB::connection('mysql_cloud')->table($table)
            ->where('uuid', $uuid)
            ->where('entreprise_id', $entrepriseId)
            ->first();

        if ($existing) {
            return ['record_uuid' => $uuid, 'status' => 'done', 'message' => 'Déjà présent'];
        }

        unset($payload['id']);
        DB::connection('mysql_cloud')->table($table)->insert($payload);

        return ['record_uuid' => $uuid, 'status' => 'done'];
    }

    private function handleUpdate(string $table, string $uuid, array $payload, string $localTime, int $entrepriseId): array
    {
        $existing = DB::connection('mysql_cloud')->table($table)
            ->where('uuid', $uuid)
            ->where('entreprise_id', $entrepriseId)
            ->first();

        if (! $existing) {
            unset($payload['id']);
            DB::connection('mysql_cloud')->table($table)->insert($payload);

            return ['record_uuid' => $uuid, 'status' => 'done'];
        }

        $cloudUpdatedAt = $existing->updated_at ?? '1970-01-01';
        if ($cloudUpdatedAt > $localTime) {
            DB::connection('mysql_cloud')->table('conflict_log')->insert([
                'table_name' => $table,
                'record_uuid' => $uuid,
                'local_payload' => json_encode($payload),
                'cloud_payload' => json_encode((array) $existing),
                'created_at' => now(),
            ]);

            return [
                'record_uuid' => $uuid,
                'status' => 'conflict',
                'message' => 'Version cloud plus récente — données locales archivées dans conflict_log',
            ];
        }

        unset($payload['id']);
        DB::connection('mysql_cloud')->table($table)
            ->where('uuid', $uuid)
            ->where('entreprise_id', $entrepriseId)
            ->update($payload);

        return ['record_uuid' => $uuid, 'status' => 'done'];
    }

    private function handleDelete(string $table, string $uuid, int $entrepriseId): array
    {
        // Vérifier que l'enregistrement appartient bien à cette entreprise avant suppression
        $exists = DB::connection('mysql_cloud')->table($table)
            ->where('uuid', $uuid)
            ->where('entreprise_id', $entrepriseId)
            ->exists();

        if (! $exists) {
            return ['record_uuid' => $uuid, 'status' => 'not_found'];
        }

        DB::connection('mysql_cloud')->table($table)
            ->where('uuid', $uuid)
            ->where('entreprise_id', $entrepriseId)
            ->delete();

        return ['record_uuid' => $uuid, 'status' => 'done'];
    }
}
