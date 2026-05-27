<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LegacySyncController extends Controller
{
    /**
     * Flux legacy web/Tauri.
     * A conserver temporairement tant que le frontend utilise encore push/pull.
     */
    private function checkQuota(int $entrepriseId, string $plan): ?JsonResponse
    {
        $quota = config("plans.{$plan}.sync_quota", -1);
        if ($quota === -1) {
            return null;
        }

        $count = Entreprise::where('id', $entrepriseId)
            ->count();

        if ($count >= $quota) {
            return response()->json([
                'error' => 'quota_exceeded',
                'quota' => $quota,
                'current' => $count,
                'message' => "Limite du plan Free atteinte ({$quota} records). Passez à Premium pour plus.",
            ], 403);
        }

        return null;
    }

    public function push(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:64'],
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.table_name' => ['required', 'string', 'max:64'],
            'operations.*.record_id' => ['required', 'uuid'],
            'operations.*.operation' => ['required', 'in:create,update,delete'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.client_sync_version' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $results = [];
        $conflicts = 0;

        $plan = auth()->user()->entreprise->plan ?? 'free';
        $quotaError = $this->checkQuota($entrepriseId, $plan);
        if ($quotaError) {
            return $quotaError;
        }

        DB::transaction(function () use ($request, $entrepriseId, &$results, &$conflicts) {
            foreach ($request->operations as $op) {
                $result = match ($op['operation']) {
                    'create', 'update' => $this->upsert($op, $entrepriseId),
                    'delete' => $this->softDelete($op, $entrepriseId),
                    default => ['status' => 'ignored'],
                };

                if (($result['status'] ?? '') === 'conflict') {
                    $conflicts++;
                }

                $results[] = array_merge(['record_id' => $op['record_id']], $result);
            }

            SyncLog::create([
                'entreprise_id' => $entrepriseId,
                'device_id' => $request->device_id,
                'direction' => 'push',
                'operations_count' => count($request->operations),
                'conflicts_count' => $conflicts,
            ]);
        });

        return response()->json([
            'results' => $results,
            'server_ts' => now()->timestamp,
            'conflicts' => $conflicts,
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'since' => ['required', 'integer', 'min:0'],
            'device_id' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $since = \Carbon\Carbon::createFromTimestamp($request->integer('since'), config('app.timezone'));

        $entities = [
            'parametres' => \App\Models\Parametre::class,
            'produits' => \App\Models\Produit::class,
            'stocks' => \App\Models\Stock::class,
            'clients' => \App\Models\Client::class,
            'fournisseurs' => \App\Models\Fournisseur::class,
            'factures' => \App\Models\Facture::class,
            'mouvement_stocks' => \App\Models\MouvementStock::class,
            'journals' => \App\Models\Journal::class,
            'employes' => \App\Models\Employe::class,
            'succursales' => \App\Models\Succursale::class,
            'caisses' => \App\Models\Caisse::class,
            'transferts' => \App\Models\Transfert::class,
            'bon_entrees' => \App\Models\BonEntree::class,
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
                ->map(fn ($r) => [
                    'uuid' => $r->uuid,
                    'sync_version' => $r->sync_version ?? 0,
                    'updated_at_ts' => $r->updated_at->timestamp,
                    'deleted_at' => $r->deleted_at?->timestamp,
                    'payload' => collect($r->toArray())
                        ->except([
                            'id', 'uuid', 'sync_version', 'entreprise_id',
                            'succursale_id', 'created_at', 'updated_at',
                            'deleted_at', 'updated_at_ts',
                        ])
                        ->toArray(),
                ]);

            $delta[$name] = $rows;
        }

        SyncLog::create([
            'entreprise_id' => $entrepriseId,
            'device_id' => $request->device_id,
            'direction' => 'pull',
            'operations_count' => collect($delta)->flatten(1)->count(),
            'conflicts_count' => 0,
        ]);

        return response()->json([
            'delta' => $delta,
            'server_ts' => now()->timestamp,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'online' => true,
            'server_ts' => now()->timestamp,
            'plan' => $user->plan ?? 'free',
            'sync_interval' => config("plans.{$user->plan}.sync_interval", null),
        ]);
    }

    private function resolveModel(string $tableName): ?string
    {
        return match ($tableName) {
            'parametres' => \App\Models\Parametre::class,
            'clients' => \App\Models\Client::class,
            'fournisseurs' => \App\Models\Fournisseur::class,
            'produits' => \App\Models\Produit::class,
            'factures' => \App\Models\Facture::class,
            'mouvement_stocks' => \App\Models\MouvementStock::class,
            'journals' => \App\Models\Journal::class,
            'employes' => \App\Models\Employe::class,
            'succursales' => \App\Models\Succursale::class,
            'caisses' => \App\Models\Caisse::class,
            'transferts' => \App\Models\Transfert::class,
            'bon_entrees' => \App\Models\BonEntree::class,
            'stocks' => \App\Models\Stock::class,
            default => null,
        };
    }

    private function resolveFields(string $tableName): array
    {
        return match ($tableName) {
            'parametres' => [
                'nom_entreprise', 'adresse', 'email', 'telephone', 'devise', 'langue',
                'rccm', 'identifiant_national', 'numero_impot', 'tva',
                'reduction_accordee', 'theme', 'message_remerciement',
                'multi_succursales', 'seuil_alerte', 'logo_path', 'logo', 'logo_position',
            ],
            'clients' => ['nom_client', 'numero_telephone', 'adresse'],
            'fournisseurs' => ['nom_entreprise_fournisseur', 'adresse', 'reduction_pourcentage'],
            'produits' => ['nom', 'prix_vente', 'prix_achat', 'quantite', 'categorie'],
            'factures' => ['total_ht', 'total_tva', 'total_ttc', 'montant_paye', 'statut', 'client_id'],
            'mouvement_stocks' => ['type', 'quantite', 'prix_unitaire', 'prix_total', 'commentaire', 'produit_id'],
            'journals' => ['dateHeure_operation', 'type', 'description', 'montant', 'produit_id'],
            'employes' => ['nom', 'prenom', 'poste', 'salaire_base', 'telephone', 'email', 'date_embauche', 'statut'],
            'succursales' => ['nom', 'adresse', 'manager_user_id', 'active'],
            'caisses' => ['date_operation', 'description', 'entree', 'sortie', 'solde', 'type_operation', 'succursale_id'],
            'transferts' => ['from_succursale_id', 'to_succursale_id', 'produit_id', 'quantite', 'statut'],
            'bon_entrees' => ['fournisseur_id', 'total_ht', 'statut', 'payment_type'],
            'stocks' => ['produit_id', 'quantite', 'seuil_alerte'],
            default => [],
        };
    }

    private function upsert(array $op, int $entrepriseId): array
    {
        $tableName = $op['table_name'] ?? null;
        $modelClass = $this->resolveModel($tableName ?? '');

        if (! $modelClass) {
            return ['status' => 'ignored', 'reason' => 'unknown_table'];
        }

        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($modelClass)
        );

        $fields = $this->resolveFields($tableName ?? '');
        $payload = collect($op['payload'])->only($fields)->toArray();
        $payload['entreprise_id'] = $entrepriseId;
        // succursale_id uniquement sur les tables qui ont cette colonne
        if (in_array('succursale_id', $fields, true)) {
            $payload['succursale_id'] = auth()->user()->succursale_id;
        }
        $payload['uuid'] = $op['record_id'];

        $mergedPayload = $usesSoftDeletes
            ? array_merge($payload, ['deleted_at' => null])
            : $payload;

        // LWW : withoutGlobalScopes() pour bypasser succursaleScoped.
        // unguarded() nécessaire : uuid n'est volontairement pas dans $fillable des modèles
        // (auto-généré par HasUuid) mais ici le client impose son UUID — il faut le respecter.
        $model = \Illuminate\Database\Eloquent\Model::unguarded(function () use ($modelClass, $op, $mergedPayload, $usesSoftDeletes) {
            return $usesSoftDeletes
                ? $modelClass::withoutGlobalScopes()->withTrashed()->updateOrCreate(['uuid' => $op['record_id']], $mergedPayload)
                : $modelClass::withoutGlobalScopes()->updateOrCreate(['uuid' => $op['record_id']], $mergedPayload);
        });

        return [
            'status' => 'synced',
            'sync_version' => $model->fresh()?->sync_version ?? 0,
        ];
    }

    private function softDelete(array $op, int $entrepriseId): array
    {
        $tableName = $op['table_name'] ?? null;
        $modelClass = $this->resolveModel($tableName ?? '');

        if (! $modelClass) {
            return ['status' => 'ignored'];
        }

        $model = $modelClass::withoutGlobalScopes()
            ->where('uuid', $op['record_id'])
            ->where('entreprise_id', $entrepriseId)
            ->first();

        if (! $model) {
            return ['status' => 'not_found'];
        }

        $model->delete();

        return ['status' => 'synced'];
    }
}
