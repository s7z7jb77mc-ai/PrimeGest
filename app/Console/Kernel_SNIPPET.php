<?php
// ─────────────────────────────────────────────────────────────
//  Ajoutez cette ligne dans app/Console/Kernel.php
//  dans la méthode protected function schedule(Schedule $schedule)
// ─────────────────────────────────────────────────────────────

// Archivage automatique chaque jour à 00:05
$schedule->command('archive:daily')->dailyAt('00:05');

// ─────────────────────────────────────────────────────────────
//  Assurez-vous que le cron Laravel tourne sur votre serveur :
//  * * * * * cd /chemin-de-votre-projet && php artisan schedule:run >> /dev/null 2>&1
// ─────────────────────────────────────────────────────────────
