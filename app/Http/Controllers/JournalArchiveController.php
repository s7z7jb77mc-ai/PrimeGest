<?php

namespace App\Http\Controllers;

use App\Models\JournalArchive;
use App\Exports\JournalExport;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class JournalArchiveController extends Controller
{
    /**
     * Afficher tous les archives groupés par date
     */
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $archiveDates = JournalArchive::where('entreprise_id', $entrepriseId)
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

        return Inertia::render('Archives/Journal', [
            'archiveDates' => $archiveDates,
        ]);
    }

    /**
     * Télécharger un archive au format Excel
     */
    public function download($date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // Vérifier que l'archive existe
        $count = JournalArchive::where('entreprise_id', $entrepriseId)
            ->where('date_archive', $date)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->count();

        if ($count === 0) {
            abort(404, 'Archive non trouvée');
        }

        $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        $filename = 'Journal_' . $dateObj->format('d-m-Y') . '.xlsx';

        return Excel::download(new JournalExport($date, $entrepriseId, $succursaleId), $filename);
    }

    /**
     * Afficher l'aperçu imprimable
     */
    public function preview($date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $mouvements = JournalArchive::where('entreprise_id', $entrepriseId)
            ->where('date_archive', $date)
            ->with(['produit', 'user'])
            ->orderBy('dateHeure_operation')
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get();

        if ($mouvements->isEmpty()) {
            abort(404, 'Archive non trouvée');
        }

        // Calculs statistiques
        $entrees = $mouvements->where('type', 'entree')->sum('montant');
        $sorties = $mouvements->where('type', 'sortie')->sum('montant');
        $total = $entrees - $sorties;

        $dateObj = Carbon::createFromFormat('Y-m-d', $date);

        return Inertia::render('Archives/JournalPreview', [
            'mouvements' => $mouvements,
            'date' => $dateObj->format('d/m/Y'),
            'jour_semaine' => $dateObj->translatedFormat('l'),
            'entrees' => $entrees,
            'sorties' => $sorties,
            'total' => $total,
        ]);
    }
}
