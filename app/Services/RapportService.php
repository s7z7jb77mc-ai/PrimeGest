<?php

namespace App\Services;

use App\Models\MouvementStockArchive;
use App\Models\JournalArchive;
use App\Models\Rapport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RapportService
{
    /**
     * Génère un rapport journalier
     */
    public function generateJournalierReport($entrepriseId, $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();
        $dateStr = $date->toDateString();

        // Vérifier si le rapport existe déjà
        $rapportExistant = Rapport::where('entreprise_id', $entrepriseId)
            ->where('type', 'journalier')
            ->where('date_debut', $dateStr)
            ->first();

        if ($rapportExistant) {
            return $rapportExistant;
        }

        // Récupérer les données
        $mouvements = MouvementStockArchive::where('entreprise_id', $entrepriseId)
            ->where('date_archive', $dateStr)
            ->with('produit')
            ->get();

        $journaux = JournalArchive::where('entreprise_id', $entrepriseId)
            ->whereDate('date_archive', $dateStr)
            ->get();

        // Calculer les résumés
        $resumeMouvements = [
            'total_entrees' => $mouvements->where('type', 'entree')->sum('quantite'),
            'total_sorties' => $mouvements->where('type', 'sortie')->sum('quantite'),
            'montant_entrees' => $mouvements->where('type', 'entree')->sum('prix_total'),
            'montant_sorties' => $mouvements->where('type', 'sortie')->sum('prix_total'),
            'nombre_mouvements' => $mouvements->count(),
        ];

        $resumeJournaux = [
            'total_montant' => $journaux->sum('montant'),
            'nombre_operations' => $journaux->count(),
            'par_type' => $journaux->groupBy('type')->mapWithKeys(function ($group, $type) {
                return [$type => [
                    'count' => $group->count(),
                    'montant' => $group->sum('montant'),
                ]];
            })->toArray(),
        ];

        // Créer le rapport
        $rapport = Rapport::create([
            'entreprise_id' => $entrepriseId,
            'type' => 'journalier',
            'date_debut' => $dateStr,
            'date_fin' => $dateStr,
            'contenu' => [
                'mouvements' => $mouvements->toArray(),
                'journaux' => $journaux->toArray(),
            ],
            'resume' => json_encode([
                'mouvements' => $resumeMouvements,
                'journaux' => $resumeJournaux,
            ]),
            'date_generation' => now(),
        ]);

        return $rapport;
    }

    /**
     * Génère un rapport hebdomadaire
     */
    public function generateHebdomadaireReport($entrepriseId, $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();
        
        // Déterminer le début et la fin de la semaine (lundi au dimanche)
        $dateDebut = $date->clone()->startOfWeek();
        $dateFin = $date->clone()->endOfWeek();

        // Vérifier si le rapport existe déjà
        $rapportExistant = Rapport::where('entreprise_id', $entrepriseId)
            ->where('type', 'hebdomadaire')
            ->where('date_debut', $dateDebut->toDateString())
            ->first();

        if ($rapportExistant) {
            return $rapportExistant;
        }

        // Récupérer les données
        $mouvements = MouvementStockArchive::where('entreprise_id', $entrepriseId)
            ->whereBetween('date_archive', [$dateDebut->toDateString(), $dateFin->toDateString()])
            ->with('produit')
            ->get();

        $journaux = JournalArchive::where('entreprise_id', $entrepriseId)
            ->whereBetween('date_archive', [$dateDebut->toDateString(), $dateFin->toDateString()])
            ->get();

        // Calculer les résumés par jour
        $mouvementsParJour = $mouvements->groupBy('date_archive')->mapWithKeys(function ($group, $date) {
            return [$date => [
                'entrees' => $group->where('type', 'entree')->sum('quantite'),
                'sorties' => $group->where('type', 'sortie')->sum('quantite'),
                'montant' => $group->sum('prix_total'),
            ]];
        })->toArray();

        $journauxParJour = $journaux->groupBy('date_archive')->mapWithKeys(function ($group, $date) {
            return [$date => [
                'montant' => $group->sum('montant'),
                'operations' => $group->count(),
            ]];
        })->toArray();

        // Créer le rapport
        $rapport = Rapport::create([
            'entreprise_id' => $entrepriseId,
            'type' => 'hebdomadaire',
            'date_debut' => $dateDebut->toDateString(),
            'date_fin' => $dateFin->toDateString(),
            'contenu' => [
                'mouvements_par_jour' => $mouvementsParJour,
                'journaux_par_jour' => $journauxParJour,
            ],
            'resume' => json_encode([
                'total_mouvements' => $mouvements->count(),
                'total_montant_mouvements' => $mouvements->sum('prix_total'),
                'total_montant_journaux' => $journaux->sum('montant'),
                'total_operations' => $journaux->count(),
            ]),
            'date_generation' => now(),
        ]);

        return $rapport;
    }

    /**
     * Génère un rapport mensuel
     */
    public function generateMensuelReport($entrepriseId, $annee = null, $mois = null)
    {
        $date = $annee && $mois ? Carbon::createFromDate($annee, $mois, 1) : now();
        
        $dateDebut = $date->clone()->startOfMonth();
        $dateFin = $date->clone()->endOfMonth();

        // Vérifier si le rapport existe déjà
        $rapportExistant = Rapport::where('entreprise_id', $entrepriseId)
            ->where('type', 'mensuel')
            ->where('date_debut', $dateDebut->toDateString())
            ->first();

        if ($rapportExistant) {
            return $rapportExistant;
        }

        // Récupérer les données
        $mouvements = MouvementStockArchive::where('entreprise_id', $entrepriseId)
            ->whereBetween('date_archive', [$dateDebut->toDateString(), $dateFin->toDateString()])
            ->with('produit')
            ->get();

        $journaux = JournalArchive::where('entreprise_id', $entrepriseId)
            ->whereBetween('date_archive', [$dateDebut->toDateString(), $dateFin->toDateString()])
            ->get();

        // Résumé par semaine
        $mouvementsParSemaine = $mouvements->groupBy(function ($item) {
            return Carbon::parse($item->date_archive)->weekOfMonth;
        })->mapWithKeys(function ($group, $semaine) {
            return ["semaine_" . $semaine => [
                'entrees' => $group->where('type', 'entree')->sum('quantite'),
                'sorties' => $group->where('type', 'sortie')->sum('quantite'),
                'montant' => $group->sum('prix_total'),
            ]];
        })->toArray();

        // Produits les plus mouvementés
        $produitsTop = $mouvements->groupBy('produit_id')
            ->map(function ($group) {
                return [
                    'quantite_total' => $group->sum('quantite'),
                    'montant_total' => $group->sum('prix_total'),
                    'nombre_mouvements' => $group->count(),
                ];
            })
            ->sortByDesc('montant_total')
            ->take(10)
            ->toArray();

        // Créer le rapport
        $rapport = Rapport::create([
            'entreprise_id' => $entrepriseId,
            'type' => 'mensuel',
            'date_debut' => $dateDebut->toDateString(),
            'date_fin' => $dateFin->toDateString(),
            'contenu' => [
                'mouvements_par_semaine' => $mouvementsParSemaine,
                'produits_top' => $produitsTop,
                'total_mouvements' => $mouvements->count(),
                'total_journaux' => $journaux->count(),
            ],
            'resume' => json_encode([
                'entrees_total' => $mouvements->where('type', 'entree')->sum('quantite'),
                'sorties_total' => $mouvements->where('type', 'sortie')->sum('quantite'),
                'montant_mouvements' => $mouvements->sum('prix_total'),
                'montant_journaux' => $journaux->sum('montant'),
                'nombre_jours_actifs' => $mouvements->pluck('date_archive')->unique()->count(),
            ]),
            'date_generation' => now(),
        ]);

        return $rapport;
    }

    /**
     * Génère tous les rapports d'une journée
     */
    public function generateAllReportsForDay($entrepriseId, $date = null)
    {
        $date = $date ? Carbon::parse($date) : now()->subDay();

        $journalier = $this->generateJournalierReport($entrepriseId, $date);
        
        // Générer aussi l'hebdomadaire si c'est le dernier jour de la semaine
        if ($date->isSunday()) {
            $this->generateHebdomadaireReport($entrepriseId, $date);
        }

        // Générer aussi le mensuel si c'est le dernier jour du mois
        if ($date->isLastDayOfMonth()) {
            $this->generateMensuelReport($entrepriseId, $date->year, $date->month);
        }

        return $journalier;
    }
}
