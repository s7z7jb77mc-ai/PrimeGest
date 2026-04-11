<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'nullable|string',
            'items' => 'required|array',
            'items.*.uuid' => 'required|string',
            'items.*.entity' => 'required|string',
            'items.*.operation' => 'required|string',
            'items.*.payload' => 'required|array',
            'items.*.occurred_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $request->input('items', []);
        $deviceId = $request->input('device_id');
        $entrepriseId = Auth::user()?->entreprise_id;
        $userId = Auth::id();

        $acked = [];
        $duplicates = [];
        $applied = 0;

        foreach ($items as $item) {
            $uuid = $item['uuid'];
            $exists = DB::table('sync_inbox')->where('uuid', $uuid)->exists();

            DB::table('sync_inbox')->updateOrInsert(
                ['uuid' => $uuid],
                [
                    'device_id' => $deviceId,
                    'entity' => $item['entity'],
                    'operation' => $item['operation'],
                    'payload' => json_encode($item['payload']),
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
            $targetTable = $this->resolveEntityTable($item['entity']);
            if ($targetTable && Schema::hasTable($targetTable)) {
                $columns = Schema::getColumnListing($targetTable);
                $payload = array_intersect_key($item['payload'], array_flip($columns));

                if (in_array('entreprise_id', $columns, true) && !isset($payload['entreprise_id'])) {
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
                } elseif (!empty($payload)) {
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
                $duplicates[] = $uuid;
            } else {
                $acked[] = $uuid;
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
