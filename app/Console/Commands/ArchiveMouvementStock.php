<?php

namespace App\Console\Commands;

use App\Models\MouvementStock;
use App\Models\MouvementStockArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveMouvementStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:mouvement-stock {--date= : Date à archiver (YYYY-MM-DD), par défaut la date d\'hier}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Archive les mouvements de stock de la veille';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Déterminer la date à archiver
        $dateToArchive = $this->option('date');
        
        if (!$dateToArchive) {
            // Par défaut, archiver la date d'hier
            $dateToArchive = Carbon::yesterday()->toDateString();
        }

        $this->info("Archivage des mouvements du jour: {$dateToArchive}");

        try {
            DB::transaction(function () use ($dateToArchive) {
                // Récupérer tous les mouvements de ce jour
                $mouvements = MouvementStock::whereDate('created_at', $dateToArchive)->get();

                if ($mouvements->isEmpty()) {
                    $this->warn("Aucun mouvement trouvé pour la date {$dateToArchive}");
                    return;
                }

                $count = 0;

                // Créer une archive pour chaque mouvement
                foreach ($mouvements as $mouvement) {
                    MouvementStockArchive::create([
                        'mouvement_stock_id' => $mouvement->id,
                        'entreprise_id'      => $mouvement->entreprise_id,
                        'produit_id'         => $mouvement->produit_id,
                        'type'               => $mouvement->type,
                        'quantite'           => $mouvement->quantite,
                        'prix_unitaire'      => $mouvement->prix_unitaire,
                        'prix_total'         => $mouvement->prix_total,
                        'user_id'            => $mouvement->user_id,
                        'commentaire'        => $mouvement->commentaire,
                        'date_archive'       => $dateToArchive,
                    ]);

                    // Supprimer le mouvement original
                    $mouvement->delete();
                    $count++;
                }

                $this->info("{$count} mouvement(s) archivé(s) avec succès");
            });

            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'archivage: " . $e->getMessage());
            return 1;
        }
    }
}
