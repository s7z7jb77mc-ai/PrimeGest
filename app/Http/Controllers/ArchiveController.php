<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $type = $request->input('type', 'journal');
        $year = $request->input('year', now()->year);

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->whereYear('date_archive', $year)
            ->orderBy('date_archive', 'desc')
            ->get(['id', 'date_archive', 'reference_id']);

        $months = collect(range(1, 12))->mapWithKeys(function ($m) use ($archives) {
            $count = $archives->filter(fn($a) => (int) $a->date_archive->format('m') === $m)->count();
            return [$m => $count];
        });

        $years = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->selectRaw('YEAR(date_archive) as y')
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->values();
        if ($years->isEmpty()) {
            $years = collect([now()->year]);
        }

        return Inertia::render('Archives/Index', [
            'type' => $type,
            'year' => (int) $year,
            'years' => $years,
            'months' => $months,
        ]);
    }

    public function month(Request $request, string $type, int $year, int $month)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->whereYear('date_archive', $year)
            ->whereMonth('date_archive', $month)
            ->orderBy('date_archive', 'desc')
            ->get(['id', 'date_archive', 'reference_id']);

        $days = $archives->groupBy(fn($a) => $a->date_archive->format('Y-m-d'))
            ->map(fn($items) => $items->count())
            ->toArray();

        return Inertia::render('Archives/Month', [
            'type' => $type,
            'year' => $year,
            'month' => $month,
            'days' => $days,
        ]);
    }

    public function day(Request $request, string $type, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj = Carbon::parse($date)->toDateString();

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->whereDate('date_archive', $dateObj)
            ->orderByDesc('id')
            ->get();

        $archivesFormatted = $this->formatArchives($type, $archives);

        return Inertia::render('Archives/Day', [
            'type' => $type,
            'date' => $dateObj,
            'archives' => $archivesFormatted,
        ]);
    }

    public function download(string $type, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj = Carbon::parse($date)->toDateString();

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->whereDate('date_archive', $dateObj)
            ->orderBy('id')
            ->get();

        $archivesFormatted = $this->formatArchives($type, $archives);
        $filename = "archive_{$type}_{$dateObj}.json";
        $content = json_encode($archivesFormatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function pdf(string $type, string $date, Request $request)
    {
        if (!in_array($type, ['journal', 'mouvement_stock', 'bon_entree', 'caisse'])) {
            abort(404);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj = Carbon::parse($date)->toDateString();
        $parametres = \App\Models\Parametre::where('entreprise_id', $entrepriseId)->first();

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', $type)
            ->whereDate('date_archive', $dateObj)
            ->orderBy('id')
            ->get();

        $formatted = $this->formatArchives($type, $archives);
        if ($type === 'bon_entree') {
            $ids = collect(explode(',', (string) $request->query('ids')))->filter()->values();
            if ($ids->isNotEmpty()) {
                $formatted = $formatted->filter(fn($b) => in_array((string) ($b['reference_id'] ?? ''), $ids->all()))->values();
            }
        }
        $view = match ($type) {
            'journal' => 'pdf.archives-journal',
            'mouvement_stock' => 'pdf.archives-mouvement-stock',
            'bon_entree' => 'pdf.archives-bon-entree',
            'caisse' => 'pdf.archives-caisse',
        };

        $pdf = app('dompdf.wrapper')
            ->loadView($view, [
                'date' => $dateObj,
                'archives' => $formatted,
                'parametres' => $parametres,
            ]);

        if ($request->query('inline') === '1') {
            return $pdf->stream("archives_{$type}_{$dateObj}.pdf");
        }

        return $pdf->download("archives_{$type}_{$dateObj}.pdf");
    }

    public function facturesPdf(Request $request, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj = Carbon::parse($date)->toDateString();
        $parametres = \App\Models\Parametre::where('entreprise_id', $entrepriseId)->first();

        $archives = Archive::where('entreprise_id', $entrepriseId)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('archives', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'facture')
            ->whereDate('date_archive', $dateObj)
            ->orderBy('id')
            ->get();

        $formatted = $this->formatArchives('facture', $archives)->values();

        $ids = collect(explode(',', (string) $request->query('ids')))->filter()->values();
        if ($ids->isNotEmpty()) {
            $formatted = $formatted->filter(fn($f) => in_array((string) ($f['reference_id'] ?? ''), $ids->all()))->values();
        }

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.archives-factures', [
                'date' => $dateObj,
                'factures' => $formatted,
                'logo_url' => $parametres?->logo_url,
                'logo_position' => $parametres?->logo_position ?? 'left',
                'parametres' => $parametres,
            ]);

        if ($request->query('inline') === '1') {
            return $pdf->stream("archives_factures_{$dateObj}.pdf");
        }

        return $pdf->download("archives_factures_{$dateObj}.pdf");
    }


    private function formatArchives(string $type, $archives)
    {
        if ($type === 'journal') {
            $userIds = $archives->pluck('payload')->pluck('user_id')->filter()->unique()->values();
            $productIds = $archives->pluck('payload')->pluck('produit_id')->filter()->unique()->values();
            $users = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');
            $products = \App\Models\Produit::whereIn('id', $productIds)->pluck('nom', 'id');

            return $archives->map(function ($a) use ($users, $products) {
                $p = $a->payload ?? [];
                $dateValue = $p['dateHeure_operation'] ?? null;
                $dateObj = $dateValue ? Carbon::parse($dateValue) : null;
                return [
                    'date' => $dateObj?->toDateString(),
                    'heure' => $dateObj?->format('H:i:s'),
                    'type' => $p['type'] ?? null,
                    'description' => $p['description'] ?? null,
                    'montant' => $p['montant'] ?? 0,
                    'user' => $users[$p['user_id'] ?? 0] ?? 'N/A',
                    'produit' => $products[$p['produit_id'] ?? 0] ?? 'N/A',
                ];
            });
        }

        if ($type === 'mouvement_stock') {
            $userIds = $archives->pluck('payload')->pluck('user_id')->filter()->unique()->values();
            $productIds = $archives->pluck('payload')->pluck('produit_id')->filter()->unique()->values();
            $users = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');
            $products = \App\Models\Produit::whereIn('id', $productIds)->pluck('nom', 'id');

            return $archives->map(function ($a) use ($users, $products) {
                $p = $a->payload ?? [];
                return [
                    'date' => $p['created_at'] ?? null,
                    'type' => $p['type'] ?? null,
                    'produit' => $products[$p['produit_id'] ?? 0] ?? 'N/A',
                    'quantite' => $p['quantite'] ?? 0,
                    'prix_unitaire' => $p['prix_unitaire'] ?? 0,
                    'prix_total' => $p['prix_total'] ?? 0,
                    'commentaire' => $p['commentaire'] ?? null,
                    'user' => $users[$p['user_id'] ?? 0] ?? 'N/A',
                ];
            });
        }

        if ($type === 'caisse') {
            return $archives->map(function ($a) {
                $p = $a->payload ?? [];
                return [
                    'reference_id' => $a->reference_id,
                    'date_operation' => $p['date_operation'] ?? null,
                    'description' => $p['description'] ?? null,
                    'entree' => $p['entree'] ?? 0,
                    'sortie' => $p['sortie'] ?? 0,
                    'solde' => $p['solde'] ?? 0,
                    'type_operation' => $p['type_operation'] ?? null,
                ];
            })->sortByDesc(function ($row) {
                $date = $row['date_operation'] ?? null;
                return $date ? strtotime((string) $date) : 0;
            })->values();
        }

        if ($type === 'transfert') {
            return $archives->map(function ($a) {
                $p = $a->payload ?? [];
                return [
                    'reference_id' => $a->reference_id,
                    'date_operation' => $p['date_operation'] ?? null,
                    'type_transfert' => $p['type'] ?? null,
                    'from_succursale' => $p['from_succursale'] ?? null,
                    'to_succursale' => $p['to_succursale'] ?? null,
                    'produit' => $p['produit'] ?? null,
                    'quantite' => $p['quantite'] ?? null,
                    'montant' => $p['montant'] ?? null,
                    'user_id' => $p['user_id'] ?? null,
                ];
            })->sortByDesc(function ($row) {
                $date = $row['date_operation'] ?? null;
                return $date ? strtotime((string) $date) : 0;
            })->values();
        }

        if ($type === 'bon_entree') {
            return $archives->map(function ($a) {
                $p = $a->payload ?? [];
                return [
                    'reference_id' => $a->reference_id,
                    'numero' => $p['numero'] ?? null,
                    'date_bon' => $p['date_bon'] ?? null,
                    'fournisseur' => $p['fournisseur'] ?? null,
                    'total' => $p['total'] ?? 0,
                    'lignes' => $p['lignes'] ?? [],
                ];
            })->sortByDesc(function ($b) {
                $date = $b['date_bon'] ?? null;
                return $date ? strtotime((string) $date) : 0;
            })->values();
        }

        // facture
        return $archives->map(function ($a) {
            $p = $a->payload ?? [];
            return [
                'reference_id' => $a->reference_id,
                'numero' => $p['numero'] ?? null,
                'date_facture' => $p['date_facture'] ?? null,
                'client' => $p['client'] ?? null,
                'client_phone' => $p['client_phone'] ?? null,
                'total_ht' => $p['total_ht'] ?? 0,
                'total_tva' => $p['total_tva'] ?? 0,
                'total_ttc' => $p['total_ttc'] ?? 0,
                'tva' => $p['tva'] ?? 0,
                'statut' => $p['statut'] ?? null,
                'lignes' => $p['lignes'] ?? [],
            ];
        })->sortByDesc(function ($f) {
            $date = $f['date_facture'] ?? null;
            return $date ? strtotime((string) $date) : 0;
        })->values();
    }
}
