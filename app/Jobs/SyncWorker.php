<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SyncQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CLOUD_API_URL = 'https://primegest.app/api/v1/sync';

    private const BATCH_SIZE = 50;

    private const PING_TIMEOUT = 5;

    private const MAX_ATTEMPTS = 5;

    public function handle(): void
    {
        if (! $this->isOnline()) {
            Log::info('SyncWorker: hors ligne, sync reportée');
            $this->reschedule();

            return;
        }

        $entries = SyncQueue::where('status', 'pending')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('created_at', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($entries->isEmpty()) {
            $this->reschedule();

            return;
        }

        $ids = $entries->pluck('id');
        SyncQueue::whereIn('id', $ids)->update(['status' => 'syncing']);

        try {
            $response = Http::withToken($this->getApiToken())
                ->timeout(30)
                ->post(self::CLOUD_API_URL, [
                    'device_id' => config('app.device_id'),
                    'batch' => $entries->map(fn ($e) => [
                        'table_name' => $e->table_name,
                        'record_uuid' => $e->record_uuid,
                        'succursale_uuid' => $e->succursale_uuid,
                        'entreprise_uuid' => $e->entreprise_uuid,
                        'operation' => $e->operation,
                        'payload' => $e->payload,
                        'checksum' => $e->checksum,
                        'local_time' => $e->created_at->toIso8601String(),
                    ])->toArray(),
                ]);

            if ($response->successful()) {
                $results = $response->json('results', []);
                $this->processResults($entries, $results);
            } else {
                SyncQueue::whereIn('id', $ids)->update([
                    'status' => 'pending',
                    'attempts' => DB::raw('attempts + 1'),
                    'error_message' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            SyncQueue::whereIn('id', $ids)->update([
                'status' => 'pending',
                'attempts' => DB::raw('attempts + 1'),
                'error_message' => $e->getMessage(),
            ]);
        }

        $this->reschedule();
    }

    private function processResults(\Illuminate\Support\Collection $entries, array $results): void
    {
        foreach ($results as $result) {
            $entry = $entries->firstWhere('record_uuid', $result['record_uuid']);
            if (! $entry) {
                continue;
            }

            match ($result['status']) {
                'done' => $entry->update([
                    'status' => 'done',
                    'synced_at' => now(),
                ]),
                'conflict' => $entry->update([
                    'status' => 'conflict',
                    'error_message' => $result['message'] ?? 'Conflit détecté',
                ]),
                default => $entry->update([
                    'status' => 'pending',
                    'attempts' => $entry->attempts + 1,
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
        $alreadyQueued = \Illuminate\Support\Facades\DB::table('jobs')
            ->where('payload', 'like', '%SyncWorker%')
            ->exists();

        if (! $alreadyQueued) {
            self::dispatch()->delay(now()->addSeconds(30));
        }
    }

    private function getApiToken(): string
    {
        $tokenFile = storage_path('app/sync_token');
        if (file_exists($tokenFile)) {
            try {
                return decrypt(trim(file_get_contents($tokenFile)));
            } catch (\Throwable) {
                // Fichier corrompu ou non chiffré — on ignore et on tombe sur le fallback
            }
        }

        return config('app.sync_token', '');
    }
}
