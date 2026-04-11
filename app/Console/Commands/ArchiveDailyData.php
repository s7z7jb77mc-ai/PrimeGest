<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Journal; // Remplacez par votre modèle pour le journal
use App\Models\MouvementStock; // Remplacez par votre modèle pour le mouvement de stock
use App\Models\JournalArchive; 
use App\Models\MouvementStockArchive; 
use Carbon\Carbon;

class ArchiveDailyData extends Command
{
    protected $signature = 'archive:daily-data';
    protected $description = 'Archive les données du journal et du mouvement de stock à la fin de la journée.';

    public function handle()
    {
        $today = Carbon::today();

        // Archivage des données du journal
        $journalEntries = Journal::whereDate('created_at', $today)->get();
        foreach ($journalEntries as $entry) {
            JournalArchive::create([
                'journal_id' => $entry->id,
                'data' => $entry->toArray(), // ou les champs spécifiques à archiver
                'archived_at' => now(),
            ]);
        }

        // Archivage des données du mouvement de stock
        $mouvementStockEntries = MouvementStock::whereDate('created_at', $today)->get();
        foreach ($mouvementStockEntries as $entry) {
            MouvementStockArchive::create([
                'mouvement_stock_id' => $entry->id,
                'data' => $entry->toArray(), // ou les champs spécifiques à archiver
                'archived_at' => now(),
            ]);
        }

        $this->info('Archivage des données du ' . $today->format('d/m/Y') . ' terminé avec succès !');
    }
}
