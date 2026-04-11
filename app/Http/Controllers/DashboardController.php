<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Journal;
use App\Models\Caisse;
use App\Models\ReportLog;
use App\Models\Succursale;
use App\Models\Facture;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasStocks = Schema::hasColumn('stocks', 'succursale_id');
        $hasMouvements = Schema::hasColumn('mouvement_stocks', 'succursale_id');
        $hasCaisses = Schema::hasColumn('caisses', 'succursale_id');
        $hasJournals = Schema::hasColumn('journals', 'succursale_id');
        $hasReportLogs = Schema::hasColumn('report_logs', 'succursale_id');
        $hasFactures = Schema::hasColumn('factures', 'succursale_id');
        $hasSuccursales = Succursale::where('entreprise_id', $entrepriseId)->exists();

        // Calcul du total des stocks (quantité * prix_vente)
        $totalStock = Stock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasStocks, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get()
            ->sum(fn($s) => $s->quantite * ($s->prix_vente ?? 0));

        // Calcul du total des ventes (sorties)
        $totalVentes = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->get()
            ->sum(fn($m) => $m->quantite * $m->prix_unitaire);

        // Calcul du total des dépenses (toutes sorties caisse)
        $totalDepenses = (float) Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasCaisses, fn($q) => $q->where('succursale_id', $succursaleId))
            ->sum('sortie');

        // Activités récentes (mouvements de stock + journal)
        $journals = Journal::with('user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasJournals, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where(function ($q) {
                $q->whereNull('description')
                  ->orWhere(function ($q) {
                      $q->where('description', 'not like', '%salaire%')
                        ->where('description', 'not like', '%paie%')
                        ->where('description', 'not like', '%paye%')
                        ->where('description', 'not like', '%Nouvelle opération%');
                  });
            })
            ->latest('dateHeure_operation')
            ->take(10)
            ->get()
            ->map(fn($j) => [
                'id' => 'j-' . $j->id,
                'description' => 'Journal: ' . ($j->description ?: ucfirst($j->type)),
                'type' => $j->type === 'entree' ? 'Entrée' : ($j->type === 'sortie' ? 'Sortie' : 'Journal'),
                'time' => $j->dateHeure_operation,
                'occurred_at' => $j->dateHeure_operation,
                'user' => $j->user->name ?? Auth::user()->name ?? 'Utilisateur inconnu',
            ]);

        $reportLogs = ReportLog::with('user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasReportLogs, fn($q) => $q->where('succursale_id', $succursaleId))
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                $label = match ($log->report_type) {
                    'facture' => 'Facture',
                    'bon_entree' => 'Bon d’entrée',
                    'journal' => 'Journal',
                    'mouvement_stock' => 'Mouvement stock',
                    default => ucfirst((string) $log->report_type),
                };
                $actionLabel = $log->action === 'download' ? 'téléchargé' : 'imprimé';
                return [
                    'id' => 'r-' . $log->id,
                    'description' => "{$label} {$actionLabel}",
                    'type' => 'Document',
                    'time' => $log->created_at,
                    'occurred_at' => $log->created_at,
                    'user' => $log->user?->name ?? 'Utilisateur inconnu',
                ];
            });

        $recentActivities = $journals->toBase()
            ->merge($reportLogs->toBase())
            ->sortByDesc(fn($a) => $a['time'])
            ->take(10)
            ->values()
            ->map(function ($a) {
                $time = is_string($a['time']) ? \Carbon\Carbon::parse($a['time']) : $a['time'];
                $now = Carbon::now();
                if ($time->greaterThan($now)) {
                    $time = $now;
                }
                $a['occurred_at'] = $time->toDateTimeString();
                return $a;
            });

        // Top produits (par quantité vendue)
        $topProduits = MouvementStock::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->get()
            ->groupBy('produit_id')
            ->map(function ($items) {
                return [
                    'nom' => $items->first()->produit->nom,
                    'quantite' => $items->sum('quantite'),
                    'montant' => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire),
                ];
            })
            ->sortByDesc('quantite')
            ->take(10)
            ->values();

        // Récupérer les paramètres (devise, langue, etc.)
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';
        $entrepriseName = $parametres?->nom_entreprise
            ?? Auth::user()->entreprise?->name
            ?? 'Entreprise';
        $langue = $parametres?->langue ?? 'fr';
        Carbon::setLocale($langue);
        $alertesStock = Stock::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasStocks, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('seuil_stock', '>', 0)
            ->whereColumn('quantite', '<=', 'seuil_stock')
            ->get()
            ->map(function ($s) {
                return [
                    'produit' => $s->produit?->nom ?? 'Produit',
                    'quantite' => $s->quantite,
                    'seuil' => $s->seuil_stock,
                ];
            })
            ->values();

        $year = Carbon::now()->year;
        $months = collect(range(1, 12))->map(fn($m) => Carbon::create($year, $m, 1));

        $salesByMonth = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->whereYear('created_at', $year)
            ->get()
            ->groupBy(fn($m) => $m->created_at->format('n'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $purchasesByMonth = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'entree')
            ->whereYear('created_at', $year)
            ->get()
            ->groupBy(fn($m) => $m->created_at->format('n'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $monthlyChart = [
            'labels' => $months->map(fn($d) => $d->translatedFormat('M'))->values(),
            'sales' => $months->map(fn($d) => (float) ($salesByMonth[$d->month] ?? 0))->values(),
            'purchases' => $months->map(fn($d) => (float) ($purchasesByMonth[$d->month] ?? 0))->values(),
            'year' => $year,
        ];

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $daysInMonth = (int) $monthEnd->format('j');
        $days = collect(range(1, $daysInMonth))->map(fn($d) => Carbon::create($monthStart->year, $monthStart->month, $d));

        $salesByDay = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get()
            ->groupBy(fn($m) => $m->created_at->format('j'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $purchasesByDay = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'entree')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get()
            ->groupBy(fn($m) => $m->created_at->format('j'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $dailyChart = [
            'labels' => $days->map(fn($d) => $d->format('d'))->values(),
            'sales' => $days->map(fn($d) => (float) ($salesByDay[$d->format('j')] ?? 0))->values(),
            'purchases' => $days->map(fn($d) => (float) ($purchasesByDay[$d->format('j')] ?? 0))->values(),
            'month' => $monthStart->translatedFormat('F'),
            'year' => $monthStart->year,
        ];

        $succursaleName = null;
        if ($succursaleId) {
            $succursaleName = Succursale::where('entreprise_id', $entrepriseId)
                ->where('id', $succursaleId)
                ->value('nom');
        }

        $pendingFactures = Facture::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasFactures, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('statut', 'en_attente')
            ->count();

        $succursaleChart = null;
        if ($hasSuccursales && $hasMouvements) {
            $rows = MouvementStock::select('succursale_id', DB::raw('SUM(quantite * prix_unitaire) as total'))
                ->where('entreprise_id', $entrepriseId)
                ->whereNotNull('succursale_id')
                ->where('type', 'sortie')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->groupBy('succursale_id')
                ->get();

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $name = Succursale::where('entreprise_id', $entrepriseId)
                    ->where('id', $row->succursale_id)
                    ->value('nom') ?? ('Succursale #' . $row->succursale_id);
                $labels[] = $name;
                $values[] = (float) $row->total;
            }
            if (!empty($labels)) {
                $succursaleChart = [
                    'labels' => $labels,
                    'values' => $values,
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'totalStock' => (float) $totalStock,
            'totalVentes' => (float) $totalVentes,
            'totalDepenses' => (float) $totalDepenses,
            'pendingFactures' => $pendingFactures,
            'authUser' => Auth::user()?->load('employe'),
            'parametres' => $parametres,
            'has_succursales' => $hasSuccursales,
            'recentActivities' => $recentActivities,
            'topProduits' => $topProduits,
            'monthlyChart' => $monthlyChart,
            'dailyChart' => $dailyChart,
            'succursaleChart' => $succursaleChart,
            'devise' => $devise,
            'alertesStock' => $alertesStock,
            'entrepriseName' => $entrepriseName,
            'succursaleName' => $succursaleName,
            'logoUrl' => $parametres?->logo_url,
        ]);
    }
}
