<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    /**
     * POST /api/sync/push
     * Reçoit un batch de mutations depuis le client Tauri.
     */
    private function checkQuota(int $entrepriseId, string $plan): ?JsonResponse
{
    $quota = config("plans.{$plan}.sync_quota", -1);
    if ($quota === -1) return null; // illimité

    $count = Entreprise::withTrashed()
        ->where('id', $entrepriseId)
        ->count();

    if ($count >= $quota) {
        return response()->json([
            'error'   => 'quota_exceeded',
            'quota'   => $quota,
            'current' => $count,
            'message' => "Limite du plan Free atteinte ({$quota} records). Passez à Premium pour plus.",
        ], 403);
    }

    return null;
} 
    public function push(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id'              => ['required', 'string', 'max:64'],
            'operations'             => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.record_id' => ['required', 'uuid'],
            'operations.*.operation' => ['required', 'in:create,update,delete'],
            'operations.*.payload'   => ['required', 'array'],
            'operations.*.client_sync_version' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $results      = [];
        $conflicts    = 0;

	$plan        = auth()->user()->entreprise->plan ?? 'free';
$quotaError  = $this->checkQuota($entrepriseId, $plan);
if ($quotaError) return $quotaError;

        DB::transaction(function () use ($request, $entrepriseId, &$results, &$conflicts) {
            foreach ($request->operations as $op) {
                $result = match ($op['operation']) {
                    'create', 'update' => $this->upsert($op, $entrepriseId),
                    'delete'           => $this->softDelete($op, $entrepriseId),
                    default            => ['status' => 'ignored'],
                };

                if (($result['status'] ?? '') === 'conflict') {
                    $conflicts++;
                }

                $results[] = array_merge(['record_id' => $op['record_id']], $result);
            }

            SyncLog::create([
                'entreprise_id'    => $entrepriseId,
                'device_id'        => $request->device_id,
                'direction'        => 'push',
                'operations_count' => count($request->operations),
                'conflicts_count'  => $conflicts,
            ]);
        });

        return response()->json([
            'results'    => $results,
            'server_ts'  => now()->timestamp,
            'conflicts'  => $conflicts,
        ]);
    }

    /**
     * GET /api/sync/pull?since=TIMESTAMP&device_id=XXX
     * Retourne le delta depuis le dernier sync du client.
     */

    public function pull(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'since'     => ['required', 'integer', 'min:0'],
        'device_id' => ['required', 'string', 'max:64'],
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $entrepriseId = auth()->user()->entreprise_id;
    $since = \Carbon\Carbon::createFromTimestamp($request->integer('since'));

    $entities = [
        'produits'     => \App\Models\Produit::class,
        'clients'      => \App\Models\Client::class,
        'fournisseurs' => \App\Models\Fournisseur::class,
    ];

    $delta = [];

    foreach ($entities as $name => $modelClass) {
        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($modelClass)
        );

        $query = $usesSoftDeletes
            ? $modelClass::withTrashed()
            : $modelClass::query();

        $rows = $query
            ->where('entreprise_id', $entrepriseId)
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->get()
	   ->map(fn($r) => [
   		 'uuid'           => $r->uuid,
   		 'sync_version'   => $r->sync_version ?? 0,
   		 'updated_at_ts'  => $r->updated_at->timestamp,
    		 'deleted_at'     => $r->deleted_at?->timestamp,
   		 'payload'        => collect($r->toArray())
                          ->except(['id', 'uuid', 'sync_version', 'entreprise_id', 
                                    'succursale_id', 'created_at', 'updated_at', 
                                    'deleted_at', 'updated_at_ts'])
                          ->toArray(),
		]);

        $delta[$name] = $rows;
    }

    SyncLog::create([
        'entreprise_id'    => $entrepriseId,
        'device_id'        => $request->device_id,
        'direction'        => 'pull',
        'operations_count' => collect($delta)->flatten(1)->count(),
        'conflicts_count'  => 0,
    ]);

    return response()->json([
        'delta'     => $delta,
        'server_ts' => now()->timestamp,
    ]);
}

    /**
     * GET /api/sync/status
     * Vérifie que le serveur est joignable + retourne infos plan.
     */
    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'online'       => true,
            'server_ts'    => now()->timestamp,
            'plan'         => $user->plan ?? 'free',
            'sync_interval'=> config("plans.{$user->plan}.sync_interval", null),
        ]);
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    private function resolveModel(string $tableName): ?string
    {
        return match($tableName) {
            'clients'      => \App\Models\Client::class,
            'fournisseurs' => \App\Models\Fournisseur::class,
            'produits'     => \App\Models\Produit::class,
            'entreprises'  => Entreprise::class,
            default        => null,
        };
    }

    private function resolveFields(string $tableName): array
    {
        return match($tableName) {
            'clients'      => ['nom_client', 'numero_telephone', 'adresse'],
            'fournisseurs' => ['nom_entreprise_fournisseur', 'adresse', 'reduction_pourcentage'],
            'produits'     => ['nom_produit', 'prix_vente', 'prix_achat', 'quantite', 'categorie'],
            default        => ['name', 'address', 'phone', 'email'],
        };
    }

    private function upsert(array $op, int $entrepriseId): array
    {
        $tableName  = $op['table_name'] ?? 'entreprises';
        $modelClass = $this->resolveModel($tableName);

        if (! $modelClass) {
            return ['status' => 'ignored', 'reason' => 'unknown_table'];
        }

        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($modelClass)
        );

        $existing = $usesSoftDeletes
            ? $modelClass::withTrashed()->find($op['record_id'])
            : $modelClass::find($op['record_id']);

        if ($existing && ($existing->sync_version ?? 0) > $op['client_sync_version']) {
            return [
                'status'       => 'conflict',
                'sync_version' => $existing->sync_version ?? 0,
            ];
        }

        $fields  = $this->resolveFields($tableName);
        $payload = collect($op['payload'])->only($fields)->toArray();
        $payload['entreprise_id'] = $entrepriseId;

        $mergedPayload = $usesSoftDeletes
            ? array_merge($payload, ['deleted_at' => null])
            : $payload;

        $model = $usesSoftDeletes
            ? $modelClass::withTrashed()->updateOrCreate(['id' => $op['record_id']], $mergedPayload)
            : $modelClass::updateOrCreate(['id' => $op['record_id']], $mergedPayload);

        return [
            'status'       => 'synced',
            'sync_version' => $model->fresh()->sync_version ?? 0,
        ];
    }

    private function softDelete(array $op, int $entrepriseId): array
    {
        $tableName  = $op['table_name'] ?? 'entreprises';
        $modelClass = $this->resolveModel($tableName);

        if (! $modelClass) {
            return ['status' => 'ignored'];
        }

        $model = $modelClass::find($op['record_id']);

        if (! $model) {
            return ['status' => 'not_found'];
        }

        if (($model->sync_version ?? 0) > $op['client_sync_version']) {
            return ['status' => 'conflict', 'sync_version' => $model->sync_version ?? 0];
        }

        $model->delete();

        return ['status' => 'synced'];
    }
}
