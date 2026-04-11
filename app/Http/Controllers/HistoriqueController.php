<?php

namespace App\Http\Controllers;

use App\Models\MouvementStockArchive;
use App\Models\JournalArchive;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class HistoriqueController extends Controller
{
    /**
     * Afficher l'historique des mouvements de stock groupés par jour
     */
    public function mouvementStock(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        
        // Récupérer les paramètres de filtrage
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $type = $request->input('type');

        $query = MouvementStockArchive::where('entreprise_id', $entrepriseId)
            ->with('produit', 'user');

        // Appliquer les filtres
        if ($dateDebut) {
            $query->whereDate('date_archive', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->whereDate('date_archive', '<=', $dateFin);
        }

        if ($type && $type !== 'tous') {
            $query->where('type', $type);
        }

        // Récupérer et grouper par date
        $mouvements = $query->orderBy('date_archive', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->date_archive->format('Y-m-d');
            })
            ->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'date_formatted' => Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y'),
                    'jour_semaine' => Carbon::createFromFormat('Y-m-d', $date)->translatedFormat('l'),
                    'mouvements' => $group->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'produit_nom' => $m->produit->nom ?? 'N/A',
                            'type' => $m->type,
                            'quantite' => $m->quantite,
                            'prix_unitaire' => $m->prix_unitaire,
                            'prix_total' => $m->prix_total,
                            'user_name' => $m->user->name ?? 'Utilisateur inconnu',
                            'commentaire' => $m->commentaire,
                            'created_at' => $m->created_at,
                        ];
                    })->toArray(),
                ];
            });

        // Calculer des statistiques
        $stats = MouvementStockArchive::where('entreprise_id', $entrepriseId);

        if ($dateDebut && $dateFin) {
            $stats = $stats->whereBetween('date_archive', [$dateDebut, $dateFin]);
        }

        $stats = [
            'total_entrees' => $stats->clone()->where('type', 'entree')->sum('quantite'),
            'total_sorties' => $stats->clone()->where('type', 'sortie')->sum('quantite'),
            'montant_entrees' => $stats->clone()->where('type', 'entree')->sum('prix_total'),
            'montant_sorties' => $stats->clone()->where('type', 'sortie')->sum('prix_total'),
        ];

        return Inertia::render('Historique/MouvementStock', [
            'archivesByDate' => $mouvements->values(),
            'stats' => $stats,
            'filtres' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'type' => $type,
            ],
        ]);
    }

    /**
     * Afficher l'historique des journaux
     */
    public function journal(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        
        // Récupérer les paramètres de filtrage
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $type = $request->input('type');

        $query = JournalArchive::where('entreprise_id', $entrepriseId);

        // Appliquer les filtres
        if ($dateDebut) {
            $query->whereDate('date_archive', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->whereDate('date_archive', '<=', $dateFin);
        }

        if ($type && $type !== 'tous') {
            $query->where('type', $type);
        }

        $journaux = $query->orderBy('date_archive', 'desc')
            ->orderBy('dateHeure_operation', 'desc')
            ->paginate(50);

        // Calculer des statistiques
        $stats = JournalArchive::where('entreprise_id', $entrepriseId);

        if ($dateDebut && $dateFin) {
            $stats = $stats->whereBetween('date_archive', [$dateDebut, $dateFin]);
        }

        $statsData = [
            'total_montant' => $stats->clone()->sum('montant'),
            'nombre_operations' => $stats->clone()->count(),
            'par_type' => $stats->clone()->groupBy('type')->mapWithKeys(function ($group, $type) {
                return [$type => [
                    'count' => $group->count(),
                    'montant' => $group->sum('montant'),
                ]];
            })->toArray(),
        ];

        return Inertia::render('Historique/Journal', [
            'journaux' => $journaux,
            'stats' => $statsData,
            'filtres' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'type' => $type,
            ],
        ]);
    }
}
