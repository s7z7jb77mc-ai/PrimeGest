<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Console\Kernel::class);
$schedule = new \Illuminate\Console\Scheduling\Schedule($app);
$kernel->schedule($schedule);

echo 'Nombre de tâches: ' . count($schedule->events()) . "\n";
foreach ($schedule->events() as $event) {
    echo 'Tâche: ' . ($event->command ?? $event->callback ?? 'N/A') . ' - Expression: ' . $event->expression . "\n";
}
