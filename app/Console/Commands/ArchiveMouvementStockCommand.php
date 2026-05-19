<?php

namespace App\Console\Commands;

use App\Models\MouvementStock;
use App\Models\MouvementStockArchive;
use App\Models\Entreprise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveMouvementStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:mouvement-stock {--date= : La date des mouvements à archiver (Y-m-d), par défaut la date d\'hier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive les mouvements de stock d\'une journée spécifiée et réinitialise la page';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateToArchive = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Archivage des mouvements de stock du {$dateToArchive}...");

        try {
            DB::transaction(function () use ($dateToArchive) {
                // Récupérer tous les mouvements du jour à archiver
                $mouvements = MouvementStock::whereDate('created_at', $dateToArchive)->get();

                $this->info("Nombre de mouvements à archiver: {$mouvements->count()}");

                foreach ($mouvements as $mouvement) {
                    // Créer l'archive
                    MouvementStockArchive::create([
                        'entreprise_id' => $mouvement->entreprise_id,
                        'produit_id' => $mouvement->produit_id,
                        'type' => $mouvement->type,
                        'quantite' => $mouvement->quantite,
                        'prix_unitaire' => $mouvement->prix_unitaire,
                        'prix_total' => $mouvement->prix_total,
                        'user_id' => $mouvement->user_id,
                        'commentaire' => $mouvement->commentaire,
                        'date_archive' => $dateToArchive,
                        'mouvement_stock_id' => $mouvement->id,
                    ]);

                    // Supprimer le mouvement original
                    $mouvement->delete();
                }

                $this->info("✓ {$mouvements->count()} mouvement(s) archivé(s) avec succès");
            });
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'archivage: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
