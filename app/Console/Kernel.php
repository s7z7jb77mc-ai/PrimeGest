<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Archivage automatique chaque jour à 23:59.
        $schedule->command('archive:unified --only=journal,mouvement_stock,facture')
            ->dailyAt('23:59')
            ->withoutOverlapping();

        // Génération des rapports chaque jour à 23:45
        $schedule->command('generate:rapports')
            ->dailyAt('23:45')
            ->withoutOverlapping();

        // Rollover mensuel de la caisse (1er jour du mois)
        $schedule->command('caisse:monthly-rollover')
            ->monthlyOn(1, '00:05')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
