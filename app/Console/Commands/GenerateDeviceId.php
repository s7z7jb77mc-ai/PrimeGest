<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateDeviceId extends Command
{
    protected $signature = 'primegest:device-id';

    protected $description = 'Générer un identifiant unique pour ce poste';

    public function handle(): void
    {
        $deviceIdFile = storage_path('app/device_id');

        if (! file_exists($deviceIdFile)) {
            $deviceId = 'device_'.Str::uuid();
            file_put_contents($deviceIdFile, $deviceId);
            $this->info("Device ID généré : {$deviceId}");
        } else {
            $this->info('Device ID existant : '.file_get_contents($deviceIdFile));
        }
    }
}
