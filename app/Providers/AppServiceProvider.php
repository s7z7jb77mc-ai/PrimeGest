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

        // Démarrer le SyncWorker uniquement quand l'app sert des requêtes HTTP
        if (! app()->runningInConsole() && config('app.env') !== 'testing') {
            SyncWorker::dispatch()->delay(now()->addSeconds(10));
        }
    }
}
