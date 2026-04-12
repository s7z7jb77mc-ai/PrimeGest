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
        $entrepriseId  = Auth::user()->entreprise_id;
        $succursaleId  = session('succursale_id');
        $hasStocks     = Schema::hasColumn('stocks', 'succursale_id');
        $hasMouvements = Schema::hasColumn('mouvement_stocks', 'succursale_id');
        $hasCaisses    = Schema::hasColumn('caisses', 'succursale_id');
        $hasJournals   = Schema::hasColumn('journals', 'succursale_id');
        $hasReportLogs = Schema::hasColumn('report_logs', 'succursale_id');
        $hasFactures   = Schema::hasColumn('factures', 'succursale_id');
        $hasSuccursales = Succursale::where('entreprise_id', $entrepriseId)->exists();

        // ── KPIs ──────────────────────────────────────────────────────────
        $totalStock = Stock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasStocks, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get()
            ->sum(fn($s) => $s->quantite * ($s->prix_vente ?? 0));

        $totalVentes = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->get()
            ->sum(fn($m) => $m->quantite * $m->prix_unitaire);

        $totalDepenses = (float) Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasCaisses, fn($q) => $q->where('succursale_id', $succursaleId))
            ->sum('sortie');

        // ── Activités récentes ────────────────────────────────────────────
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
            ->map(function ($j) use ($succursaleId, $hasJournals) {
                $description = 'Journal: ' . ($j->description ?: ucfirst($j->type));
                if (!$succursaleId && $hasJournals && $j->succursale_id) {
                    $nomSucc = Succursale::find($j->succursale_id)?->nom;
                    if ($nomSucc) $description = "[{$nomSucc}] " . $description;
                }
                return [
                    'id'          => 'j-' . $j->id,
                    'description' => $description,
                    'type'        => $j->type === 'entree' ? 'Entrée' : ($j->type === 'sortie' ? 'Sortie' : 'Journal'),
                    'time'        => $j->dateHeure_operation,
                    'occurred_at' => $j->dateHeure_operation,
                    'user'        => $j->user->name ?? Auth::user()->name ?? 'Utilisateur inconnu',
                ];
            });

        $reportLogs = ReportLog::with('user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasReportLogs, fn($q) => $q->where('succursale_id', $succursaleId))
            ->latest()->take(10)->get()
            ->map(function ($log) {
                $label = match ($log->report_type) {
                    'facture'         => 'Facture',
                    'bon_entree'      => 'Bon d\'entrée',
                    'journal'         => 'Journal',
                    'mouvement_stock' => 'Mouvement stock',
                    default           => ucfirst((string) $log->report_type),
                };
                return [
                    'id'          => 'r-' . $log->id,
                    'description' => "{$label} " . ($log->action === 'download' ? 'téléchargé' : 'imprimé'),
                    'type'        => 'Document',
                    'time'        => $log->created_at,
                    'occurred_at' => $log->created_at,
                    'user'        => $log->user?->name ?? 'Utilisateur inconnu',
                ];
            });

        $recentActivities = $journals->toBase()
            ->merge($reportLogs->toBase())
            ->sortByDesc(fn($a) => $a['time'])
            ->take(10)->values()
            ->map(function ($a) {
                $time = is_string($a['time']) ? Carbon::parse($a['time']) : $a['time'];
                if ($time->greaterThan(Carbon::now())) $time = Carbon::now();
                $a['occurred_at'] = $time->toDateTimeString();
                return $a;
            });

        // ── Top produits ──────────────────────────────────────────────────
        $topProduits = MouvementStock::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')->get()
            ->groupBy('produit_id')
            ->map(fn($items) => [
                'nom'      => $items->first()->produit->nom,
                'quantite' => $items->sum('quantite'),
                'montant'  => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire),
            ])
            ->sortByDesc('quantite')->take(10)->values();

        // ── Paramètres ────────────────────────────────────────────────────
        $parametres     = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise         = $parametres?->devise ?? 'CDF';
        $entrepriseName = $parametres?->nom_entreprise ?? Auth::user()->entreprise?->name ?? 'Entreprise';
        $langue         = $parametres?->langue ?? 'fr';
        Carbon::setLocale($langue);

        // ── Alertes stock ─────────────────────────────────────────────────
        // Dashboard central → toutes les succursales, avec nom succursale dans l'alerte
        // Dashboard succursale → filtrée sur la succursale active uniquement
        if (!$succursaleId && $hasStocks) {
            $alertesStock = Stock::with(['produit', 'succursale'])
                ->where('entreprise_id', $entrepriseId)
                ->where('seuil_stock', '>', 0)
                ->whereColumn('quantite', '<=', 'seuil_stock')
                ->get()
                ->map(fn($s) => [
                    'produit'    => $s->produit?->nom ?? 'Produit',
                    'succursale' => $s->succursale?->nom ?? 'Central',
                    'quantite'   => $s->quantite,
                    'seuil'      => $s->seuil_stock,
                ])
                ->values();
        } else {
            $alertesStock = Stock::with('produit')
                ->where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $hasStocks, fn($q) => $q->where('succursale_id', $succursaleId))
                ->where('seuil_stock', '>', 0)
                ->whereColumn('quantite', '<=', 'seuil_stock')
                ->get()
                ->map(fn($s) => [
                    'produit'    => $s->produit?->nom ?? 'Produit',
                    'succursale' => null,
                    'quantite'   => $s->quantite,
                    'seuil'      => $s->seuil_stock,
                ])
                ->values();
        }

        // ── Aperçu stock au dashboard central : consolidé par produit ─────
        // ✅ Au central, on somme les quantités de toutes les succursales
        // pour éviter les doublons dans le tableau d'aperçu.
        // (chaque succursale qui achète un produit crée sa propre ligne Stock)

        // ── Graphiques ────────────────────────────────────────────────────
        $year   = Carbon::now()->year;
        $months = collect(range(1, 12))->map(fn($m) => Carbon::create($year, $m, 1));

        $salesByMonth = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')->whereYear('created_at', $year)->get()
            ->groupBy(fn($m) => $m->created_at->format('n'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $purchasesByMonth = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'entree')->whereYear('created_at', $year)->get()
            ->groupBy(fn($m) => $m->created_at->format('n'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $monthlyChart = [
            'labels'    => $months->map(fn($d) => $d->translatedFormat('M'))->values(),
            'sales'     => $months->map(fn($d) => (float) ($salesByMonth[$d->month] ?? 0))->values(),
            'purchases' => $months->map(fn($d) => (float) ($purchasesByMonth[$d->month] ?? 0))->values(),
            'year'      => $year,
        ];

        $monthStart  = Carbon::now()->startOfMonth();
        $monthEnd    = Carbon::now()->endOfMonth();
        $daysInMonth = (int) $monthEnd->format('j');
        $days        = collect(range(1, $daysInMonth))->map(fn($d) => Carbon::create($monthStart->year, $monthStart->month, $d));

        $salesByDay = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')->whereBetween('created_at', [$monthStart, $monthEnd])->get()
            ->groupBy(fn($m) => $m->created_at->format('j'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $purchasesByDay = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'entree')->whereBetween('created_at', [$monthStart, $monthEnd])->get()
            ->groupBy(fn($m) => $m->created_at->format('j'))
            ->map(fn($items) => $items->sum(fn($m) => $m->quantite * $m->prix_unitaire));

        $dailyChart = [
            'labels'    => $days->map(fn($d) => $d->format('d'))->values(),
            'sales'     => $days->map(fn($d) => (float) ($salesByDay[$d->format('j')] ?? 0))->values(),
            'purchases' => $days->map(fn($d) => (float) ($purchasesByDay[$d->format('j')] ?? 0))->values(),
            'month'     => $monthStart->translatedFormat('F'),
            'year'      => $monthStart->year,
        ];

        // ── Succursale active ─────────────────────────────────────────────
        $succursaleName = null;
        if ($succursaleId) {
            $succursaleName = Succursale::where('entreprise_id', $entrepriseId)
                ->where('id', $succursaleId)->value('nom');
        }

        $pendingFactures = Facture::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasFactures, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('statut', 'en_attente')->count();

        // ── Diagramme succursales (dashboard central uniquement) ──────────
        $succursaleChart = null;
        if (!$succursaleId && $hasSuccursales && $hasMouvements) {
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
                $name     = Succursale::where('entreprise_id', $entrepriseId)->where('id', $row->succursale_id)->value('nom') ?? ('Succursale #' . $row->succursale_id);
                $labels[] = $name;
                $values[] = (float) $row->total;
            }
            if (!empty($labels)) {
                $succursaleChart = ['labels' => $labels, 'values' => $values];
            }
        }

        return Inertia::render('Dashboard', [
            'totalStock'       => (float) $totalStock,
            'totalVentes'      => (float) $totalVentes,
            'totalDepenses'    => (float) $totalDepenses,
            'pendingFactures'  => $pendingFactures,
            'authUser'         => Auth::user()?->load('employe'),
            'parametres'       => $parametres,
            'has_succursales'  => $hasSuccursales,
            'recentActivities' => $recentActivities,
            'topProduits'      => $topProduits,
            'monthlyChart'     => $monthlyChart,
            'dailyChart'       => $dailyChart,
            'succursaleChart'  => $succursaleChart,
            'devise'           => $devise,
            'alertesStock'     => $alertesStock,
            'entrepriseName'   => $entrepriseName,
            'succursaleName'   => $succursaleName,
            'logoUrl'          => $parametres?->logo_url,
        ]);
    }
}