<?php

namespace App\Console\Commands;

use App\Models\Entreprise;
use App\Services\RapportService;
use Illuminate\Console\Command;

class GenerateRapportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:rapports {--date= : La date pour laquelle générer les rapports (Y-m-d), par défaut la date d\'hier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère automatiquement les rapports journaliers, hebdomadaires et mensuels';

    protected $rapportService;

    public function __construct(RapportService $rapportService)
    {
        parent::__construct();
        $this->rapportService = $rapportService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now()->subDay();

        $this->info("Génération des rapports pour le {$date->toDateString()}...");

        try {
            // Générer les rapports pour chaque entreprise
            $entreprises = Entreprise::all();

            foreach ($entreprises as $entreprise) {
                $this->info("Génération pour l'entreprise: {$entreprise->nom}");
                $this->rapportService->generateAllReportsForDay($entreprise->id, $date);
                $this->info("✓ Rapports générés pour {$entreprise->nom}");
            }

            $this->info("✓ Tous les rapports ont été générés avec succès");
        } catch (\Exception $e) {
            $this->error("Erreur lors de la génération des rapports: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
