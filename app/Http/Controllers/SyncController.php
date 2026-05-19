<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $items = collect($request->input('items', []))
            ->map(function (array $item) {
                $recordUuid = $item['record_uuid'] ?? $item['uuid'] ?? null;
                $tableName = $item['table_name'] ?? $item['entity'] ?? null;
                $payload = $item['payload'] ?? [];

                return [
                    'record_uuid' => $recordUuid,
                    'table_name' => $tableName,
                    'operation' => $item['operation'] ?? null,
                    'payload' => $payload,
                    'occurred_at' => $item['occurred_at'] ?? null,
                    'checksum' => $item['checksum'] ?? hash('sha256', json_encode($payload)),
                ];
            })
            ->values()
            ->all();

        $validator = Validator::make([
            'device_id' => $request->input('device_id'),
            'items' => $items,
        ], [
            'device_id' => 'nullable|string',
            'items' => 'required|array',
            'items.*.record_uuid' => 'required|string',
            'items.*.table_name' => 'required|string',
            'items.*.operation' => 'required|string',
            'items.*.payload' => 'required|array',
            'items.*.occurred_at' => 'nullable|date',
            'items.*.checksum' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $deviceId = $request->input('device_id');
        $entrepriseId = Auth::user()?->entreprise_id;
        $userId = Auth::id();

        $acked = [];
        $duplicates = [];
        $applied = 0;

        foreach ($items as $item) {
            $recordUuid = $item['record_uuid'];
            $exists = DB::table('sync_inbox')->where('record_uuid', $recordUuid)->exists();

            DB::table('sync_inbox')->updateOrInsert(
                ['record_uuid' => $recordUuid],
                [
                    'device_id' => $deviceId,
                    'table_name' => $item['table_name'],
                    'operation' => $item['operation'],
                    'payload' => json_encode($item['payload']),
                    'checksum' => $item['checksum'],
                    'occurred_at' => $item['occurred_at'] ?? null,
                    'received_at' => now(),
                    'user_id' => $userId,
                    'entreprise_id' => $entrepriseId,
                    'status' => 'received',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Upsert dans la base cloud (MySQL) si table cible connue
            $targetTable = $this->resolveEntityTable($item['table_name']);
            if ($targetTable && Schema::hasTable($targetTable)) {
                $columns = Schema::getColumnListing($targetTable);
                $payload = array_intersect_key($item['payload'], array_flip($columns));

                $computedChecksum = hash('sha256', json_encode($item['payload']));
                if ($computedChecksum !== $item['checksum']) {
                    throw ValidationException::withMessages([
                        'items' => 'Checksum invalide pour une opération de synchronisation.',
                    ]);
                }

                if (in_array('entreprise_id', $columns, true) && ! isset($payload['entreprise_id'])) {
                    $payload['entreprise_id'] = $entrepriseId;
                }

                $keyName = null;
                $keyValue = null;
                if (isset($item['payload']['uuid']) && in_array('uuid', $columns, true)) {
                    $keyName = 'uuid';
                    $keyValue = $item['payload']['uuid'];
                } elseif (isset($item['payload']['id']) && in_array('id', $columns, true)) {
                    $keyName = 'id';
                    $keyValue = $item['payload']['id'];
                }

                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = now();
                }

                if ($keyName && $keyValue !== null) {
                    $query = DB::table($targetTable)->where($keyName, $keyValue);
                    if (in_array('entreprise_id', $columns, true) && $entrepriseId) {
                        $query->where('entreprise_id', $entrepriseId);
                    }
                    $rowExists = $query->exists();
                    if ($rowExists) {
                        $query->update($payload);
                    } else {
                        if (in_array('created_at', $columns, true)) {
                            $payload['created_at'] = now();
                        }
                        DB::table($targetTable)->insert($payload);
                    }
                    $applied++;
                } elseif (! empty($payload)) {
                    if (in_array('created_at', $columns, true)) {
                        $payload['created_at'] = now();
                    }
                    if (in_array('updated_at', $columns, true)) {
                        $payload['updated_at'] = now();
                    }
                    DB::table($targetTable)->insert($payload);
                    $applied++;
                }
            }

            if ($exists) {
                $duplicates[] = $recordUuid;
            } else {
                $acked[] = $recordUuid;
            }
        }

        return response()->json([
            'ok' => true,
            'acked' => $acked,
            'duplicates' => $duplicates,
            'received' => count($acked),
            'applied' => $applied,
            'server_time' => now()->toDateTimeString(),
            'user_id' => Auth::id(),
        ]);
    }

    private function resolveEntityTable(string $entity): ?string
    {
        $map = [
            'clients' => 'clients',
            'fournisseurs' => 'fournisseurs',
            'produits' => 'produits',
            'mouvement_stocks' => 'mouvement_stocks',
            'journals' => 'journals',
            'factures' => 'factures',
            'caisses' => 'caisses',
        ];

        return $map[$entity] ?? $entity;
    }
}
