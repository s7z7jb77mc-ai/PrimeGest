<?php

namespace App\Console\Commands;

use App\Models\MouvementStockArchive;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateTestArchives extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:test-archives {--days=5 : Nombre de jours à générer}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Génère des archives de test pour les jours précédents';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int)$this->option('days');
        
        // Récupérer l'entreprise de l'utilisateur actuel ou utiliser 4
        $currentUser = \App\Models\User::first();
        $entrepriseId = $currentUser ? $currentUser->entreprise_id : 4;

        // Récupérer les IDs de produits et utilisateurs valides
        $productIds = \App\Models\Produit::where('entreprise_id', $entrepriseId)->pluck('id')->toArray();
        $userIds = \App\Models\User::where('entreprise_id', $entrepriseId)->pluck('id')->toArray();
        
        if (empty($productIds)) {
            $this->error("Aucun produit trouvé pour l'entreprise $entrepriseId");
            return 1;
        }
        if (empty($userIds)) {
            $this->error("Aucun utilisateur trouvé pour l'entreprise $entrepriseId");
            return 1;
        }

        $this->info("Génération de $days jours d'archives de test pour l'entreprise $entrepriseId");

        for ($i = 1; $i <= $days; $i++) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $count = MouvementStockArchive::whereDate('date_archive', $date)
                ->where('entreprise_id', $entrepriseId)
                ->count();

            if ($count > 0) {
                $this->warn("Date $date a déjà des archives ($count)");
                continue;
            }

            // Créer 3-5 mouvements de test par jour
            $numMovements = rand(3, 5);
            for ($j = 0; $j < $numMovements; $j++) {
                MouvementStockArchive::create([
                    'mouvement_stock_id' => null,
                    'entreprise_id'      => $entrepriseId,
                    'produit_id'         => $productIds[array_rand($productIds)],
                    'type'               => rand(0, 1) ? 'entree' : 'sortie',
                    'quantite'           => rand(5, 50),
                    'prix_unitaire'      => rand(1000, 10000),
                    'prix_total'         => rand(5000, 500000),
                    'user_id'            => $userIds[array_rand($userIds)],
                    'commentaire'        => 'Archive de test',
                    'date_archive'       => $date,
                ]);
            }

            $this->info("  ✓ $numMovements mouvements créés pour le $date");
        }

        $this->info("Archives de test générées avec succès!");
        return 0;
    }
}
