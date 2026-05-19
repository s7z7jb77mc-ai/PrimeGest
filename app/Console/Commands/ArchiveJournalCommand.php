<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Models\JournalArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveJournalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:journal {--date= : La date des journaux à archiver (Y-m-d), par défaut la date d\'hier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive les journaux d\'une journée spécifiée et réinitialise le journal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateToArchive = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Archivage des journaux du {$dateToArchive}...");

        try {
            DB::transaction(function () use ($dateToArchive) {
                // Récupérer tous les journaux du jour à archiver
                $journaux = Journal::whereDate('dateHeure_operation', $dateToArchive)->get();

                $this->info("Nombre de journaux à archiver: {$journaux->count()}");

                foreach ($journaux as $journal) {
                    // Créer l'archive
                    JournalArchive::create([
                        'entreprise_id' => $journal->entreprise_id,
                        'produit_id' => $journal->produit_id,
                        'dateHeure_operation' => $journal->dateHeure_operation,
                        'type' => $journal->type,
                        'description' => $journal->description,
                        'montant' => $journal->montant,
                        'date_archive' => $dateToArchive,
                        'journal_id' => $journal->id,
                    ]);

                    // Supprimer le journal original
                    $journal->delete();
                }

                $this->info("✓ {$journaux->count()} journal(aux) archivé(s) avec succès");
            });
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'archivage: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
