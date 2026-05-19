<?php

namespace App\Console\Commands;

use App\Models\Caisse;
use App\Models\Entreprise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaisseMonthlyRollover extends Command
{
    protected $signature = 'caisse:monthly-rollover';
    protected $description = 'Réinitialiser la caisse en fin de mois et créer le solde initial du mois suivant.';

    public function handle(): int
    {
        if (!Schema::hasColumn('caisses', 'type_operation')) {
            $this->warn('Colonne type_operation absente. Rollover ignoré.');
            return self::SUCCESS;
        }

        $now = now();
        $entreprises = Entreprise::query()->pluck('id');

        foreach ($entreprises as $entrepriseId) {
            DB::transaction(function () use ($entrepriseId, $now) {
                $current = (float) Caisse::where('entreprise_id', $entrepriseId)
                    ->lockForUpdate()
                    ->selectRaw('COALESCE(SUM(entree - sortie), 0) as solde')
                    ->value('solde');

                // Purger l'historique de caisse du mois précédent
                Caisse::where('entreprise_id', $entrepriseId)->delete();

                Caisse::create([
                    'entreprise_id' => $entrepriseId,
                    'date_operation' => $now,
                    'description' => 'Solde initial (rollover mensuel)',
                    'entree' => $current,
                    'sortie' => 0,
                    'solde' => $current,
                    'type_operation' => 'initial',
                ]);
            });
        }

        $this->info('Rollover mensuel de la caisse terminé.');
        return self::SUCCESS;
    }
}
