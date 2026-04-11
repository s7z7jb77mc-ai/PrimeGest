<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Employe;
use App\Models\FicheDePaie;
use App\Models\MouvementStock;
use App\Models\Journal;
use App\Observers\EmployeObserver;
use App\Observers\FicheDePaieObserver;
use App\Observers\MouvementStockObserver;
use App\Observers\JournalObserver;

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
    }
}
