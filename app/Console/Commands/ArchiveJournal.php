<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Models\JournalArchive;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveJournal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:journal {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive yesterday\'s journal entries at end of day (23:30)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateStr = $this->option('date') ?? Carbon::yesterday()->format('Y-m-d');
        $date = Carbon::createFromFormat('Y-m-d', $dateStr);

        DB::transaction(function () use ($date, $dateStr) {
            // Récupérer tous les journaux d'hier
            $journaux = Journal::where(DB::raw('DATE(created_at)'), $dateStr)
                ->get();

            if ($journaux->isEmpty()) {
                $this->info("✓ Aucun journal à archiver pour le $dateStr");
                return;
            }

            // Copier dans les archives
            foreach ($journaux as $journal) {
                JournalArchive::create([
                    'entreprise_id' => $journal->entreprise_id,
                    'produit_id' => $journal->produit_id,
                    'user_id' => $journal->user_id,
                    'dateHeure_operation' => $journal->dateHeure_operation,
                    'date_archive' => $date->format('Y-m-d'),
                    'type' => $journal->type,
                    'description' => $journal->description,
                    'montant' => $journal->montant,
                ]);
            }

            // Supprimer les journaux d'hier
            Journal::where(DB::raw('DATE(created_at)'), $dateStr)->delete();

            $count = $journaux->count();
            $this->info("✓ $count journal(s) archivé(s) pour le $dateStr");
        });
    }
}

