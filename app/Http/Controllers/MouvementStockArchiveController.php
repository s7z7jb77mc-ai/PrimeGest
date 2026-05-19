<?php

namespace App\Http\Controllers;

use App\Models\MouvementStockArchive;
use App\Exports\MouvementStockExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class MouvementStockArchiveController extends Controller
{
    /**
     * Afficher la liste des fichiers d'archives
     */
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // Récupérer les dates uniques avec archives
        $archiveDates = MouvementStockArchive::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get()
            ->groupBy(function ($item) {
                return $item->date_archive->format('Y-m-d');
            })
            ->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'date_formatted' => Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y'),
                    'jour_semaine' => Carbon::createFromFormat('Y-m-d', $date)->translatedFormat('l'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Archives/MouvementStock', [
            'archiveDates' => $archiveDates,
        ]);
    }

    /**
     * Télécharger un fichier Excel pour une date donnée
     */
    public function download($date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // Vérifier que l'utilisateur a accès à cette date
        $count = MouvementStockArchive::whereDate('date_archive', $date)
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->count();

        if ($count === 0) {
            abort(404, 'Aucun mouvement trouvé pour cette date');
        }

        try {
            $filename = "mouvements_stock_{$date}.xlsx";
            return Excel::download(new MouvementStockExport($date, $succursaleId), $filename);
        } catch (\Exception $e) {
            abort(500, 'Erreur lors du téléchargement du fichier');
        }
    }

    /**
     * Afficher l'aperçu pour impression
     */
    public function preview($date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // Vérifier que l'utilisateur a accès à cette date
        $mouvements = MouvementStockArchive::with('produit', 'user')
            ->whereDate('date_archive', $date)
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get();

        if ($mouvements->isEmpty()) {
            abort(404, 'Aucun mouvement trouvé pour cette date');
        }

        $mouvementsFormatted = $mouvements->map(function ($m) {
            return [
                'id' => $m->id,
                'produit_nom' => $m->produit->nom ?? 'N/A',
                'type' => $m->type,
                'quantite' => $m->quantite,
                'prix_unitaire' => $m->prix_unitaire,
                'prix_total' => $m->prix_total,
                'user_name' => $m->user->name ?? 'Inconnu',
                'commentaire' => $m->commentaire,
                'created_at' => $m->created_at,
            ];
        });

        $dateObj = Carbon::createFromFormat('Y-m-d', $date);

        return Inertia::render('Archives/MouvementStockPreview', [
            'date' => $date,
            'date_formatted' => $dateObj->format('d/m/Y'),
            'jour_semaine' => $dateObj->translatedFormat('l'),
            'mouvements' => $mouvementsFormatted,
            'total_entrees' => $mouvements->where('type', 'entree')->sum('quantite'),
            'total_sorties' => $mouvements->where('type', 'sortie')->sum('quantite'),
            'montant_entrees' => $mouvements->where('type', 'entree')->sum('prix_total'),
            'montant_sorties' => $mouvements->where('type', 'sortie')->sum('prix_total'),
        ]);
    }
}
