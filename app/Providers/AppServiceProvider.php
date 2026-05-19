<?php

namespace App\Providers;

use App\Jobs\SyncWorker;
use App\Models\Employe;
use App\Models\FicheDePaie;
use App\Models\Journal;
use App\Models\MouvementStock;
use App\Observers\EmployeObserver;
use App\Observers\FicheDePaieObserver;
use App\Observers\JournalObserver;
use App\Observers\MouvementStockObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        $this->loadMigrationsFrom(database_path('factories/mgr'));

        // Enregistrer les Observers pour l'automatisation
        Employe::observe(EmployeObserver::class);
        FicheDePaie::observe(FicheDePaieObserver::class);
        MouvementStock::observe(MouvementStockObserver::class);
        Journal::observe(JournalObserver::class);

        // Démarrer le SyncWorker uniquement si des données sont en attente
        // et qu'aucun job SyncWorker n'est déjà en queue (évite les doublons)
        if (! app()->runningInConsole() && config('app.env') !== 'testing') {
            try {
                $alreadyQueued = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('payload', 'like', '%SyncWorker%')
                    ->exists();

                $hasPending = \Illuminate\Support\Facades\DB::table('sync_queue')
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPending && ! $alreadyQueued) {
                    SyncWorker::dispatch()->delay(now()->addSeconds(10));
                }
            } catch (\Throwable) {
                // Tables pas encore créées (premier démarrage avant migrate)
            }
        }
    }
}
