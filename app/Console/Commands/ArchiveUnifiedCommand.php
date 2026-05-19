<?php

namespace App\Console\Commands;

use App\Models\Archive;
use App\Models\Facture;
use App\Models\Journal;
use App\Models\MouvementStock;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveUnifiedCommand extends Command
{
    protected $signature = 'archive:unified {--date= : Date à archiver (Y-m-d), par défaut hier} {--all : Archiver toutes les dates disponibles} {--only= : Types à archiver (journal,mouvement_stock,facture)}';

    protected $description = 'Archive journaux, mouvements de stock et factures dans la table unique archives';

    public function handle()
    {
        $only = $this->parseOnly();

        if ($this->option('all')) {
            return $this->archiveAllDates($only);
        }

        $date = $this->option('date') ?? now()->subDay()->toDateString();
        return $this->archiveDate($date, $only);
    }

    private function archiveAllDates(array $only)
    {
        $dates = collect();

        if (in_array('journal', $only, true)) {
            $dates = $dates->merge(
                Journal::selectRaw('DATE(dateHeure_operation) as d')->distinct()->pluck('d')
            );
        }
        if (in_array('mouvement_stock', $only, true)) {
            $dates = $dates->merge(
                MouvementStock::selectRaw('DATE(created_at) as d')->distinct()->pluck('d')
            );
        }
        if (in_array('facture', $only, true)) {
            $dates = $dates->merge(
                Facture::selectRaw('DATE(date_facture) as d')->distinct()->pluck('d')
            );
        }

        $dates = $dates->filter()->unique()->sort();

        foreach ($dates as $d) {
            $this->archiveDate((string) $d, $only);
        }

        return Command::SUCCESS;
    }

    private function archiveDate(string $date, array $only)
    {
        $dateObj = Carbon::parse($date)->toDateString();

        $this->info("Archivage unifié pour {$dateObj}...");

        DB::transaction(function () use ($dateObj, $only) {
            // Journaux
            if (in_array('journal', $only, true)) {
                $journaux = Journal::whereDate('dateHeure_operation', $dateObj)->get();
                foreach ($journaux as $j) {
                    $this->storeArchive('journal', $dateObj, $j->entreprise_id, (string) $j->id, [
                        'id' => $j->id,
                        'produit_id' => $j->produit_id,
                        'dateHeure_operation' => $j->dateHeure_operation,
                        'type' => $j->type,
                        'description' => $j->description,
                        'montant' => $j->montant,
                        'user_id' => $j->user_id,
                    ]);
                    $j->delete();
                }
            }

            // Mouvements de stock
            if (in_array('mouvement_stock', $only, true)) {
                $mouvements = MouvementStock::whereDate('created_at', $dateObj)->get();
                foreach ($mouvements as $m) {
                    $this->storeArchive('mouvement_stock', $dateObj, $m->entreprise_id, (string) $m->id, [
                        'id' => $m->id,
                        'produit_id' => $m->produit_id,
                        'type' => $m->type,
                        'quantite' => $m->quantite,
                        'prix_unitaire' => $m->prix_unitaire,
                        'prix_total' => $m->prix_total,
                        'user_id' => $m->user_id,
                        'commentaire' => $m->commentaire,
                        'created_at' => $m->created_at,
                    ]);
                    $m->delete();
                }
            }

            // Factures (archive copie)
            if (in_array('facture', $only, true)) {
                $factures = Facture::with('lignes')
                    ->whereDate('date_facture', $dateObj)
                    ->get();
                foreach ($factures as $f) {
                    $this->storeArchive('facture', $dateObj, $f->entreprise_id, (string) $f->id, [
                        'id' => $f->id,
                        'numero' => $f->numero,
                        'date_facture' => $f->date_facture,
                        'total_ht' => $f->total_ht,
                        'total_tva' => $f->total_tva,
                        'total_ttc' => $f->total_ttc ?? $f->total_montant,
                        'tva' => $f->tva,
                        'statut' => $f->statut,
                        'lignes' => $f->lignes->map(fn($l) => [
                            'designation' => $l->designation,
                            'quantite' => $l->quantite,
                            'prix_ttc' => $l->prix_ttc,
                            'total' => $l->total,
                        ])->toArray(),
                    ]);
                }
            }
        });

        $this->info("✓ Archivage terminé pour {$dateObj}");

        return Command::SUCCESS;
    }

    private function parseOnly(): array
    {
        $only = $this->option('only');
        if (!$only) {
            return ['journal', 'mouvement_stock', 'facture'];
        }
        return collect(explode(',', $only))
            ->map(fn($t) => trim($t))
            ->filter()
            ->values()
            ->all();
    }

    private function storeArchive(string $type, string $date, int $entrepriseId, ?string $referenceId, array $payload): void
    {
        $exists = Archive::where('entreprise_id', $entrepriseId)
            ->where('type', $type)
            ->where('date_archive', $date)
            ->where('reference_id', $referenceId)
            ->exists();

        if ($exists) {
            return;
        }

        Archive::create([
            'entreprise_id' => $entrepriseId,
            'type' => $type,
            'date_archive' => $date,
            'reference_id' => $referenceId,
            'payload' => $payload,
        ]);
    }
}
