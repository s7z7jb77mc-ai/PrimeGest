<?php

namespace App\Console\Commands;

use App\Models\MouvementStockArchive;
use App\Exports\MouvementStockExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class GenerateMouvementStockFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:mouvement-stock-file {--date= : Date du fichier (YYYY-MM-DD), par défaut la date d\'hier}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Génère un fichier Excel pour les mouvements de stock du jour';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Déterminer la date
        $date = $this->option('date');
        
        if (!$date) {
            $date = Carbon::yesterday()->toDateString();
        }

        $this->info("Génération du fichier pour le jour: {$date}");

        try {
            // Vérifier s'il y a des mouvements pour cette date
            $count = MouvementStockArchive::whereDate('date_archive', $date)->count();

            if ($count === 0) {
                $this->warn("Aucun mouvement trouvé pour la date {$date}");
                return 0;
            }

            // Créer le dossier s'il n'existe pas
            $path = 'mouvements-stock/' . $date;
            if (!Storage::disk('local')->exists($path)) {
                Storage::disk('local')->makeDirectory($path, 0755, true);
            }

            // Générer le fichier Excel
            $filename = "mouvements_stock_{$date}.xlsx";
            $filepath = $path . '/' . $filename;

            Excel::store(new MouvementStockExport($date), $filepath, 'local');

            $this->info("Fichier généré avec succès: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur lors de la génération du fichier: " . $e->getMessage());
            return 1;
        }
    }
}
