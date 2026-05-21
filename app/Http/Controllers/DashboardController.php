<?php

namespace App\Http\Controllers;

use App\Models\Caisse;
use App\Models\Facture;
use App\Models\Journal;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\ReportLog;
use App\Models\Stock;
use App\Models\Succursale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user()->load('employe');
        $entrepriseId = $user->entreprise_id;
        $succursaleId = session('succursale_id');

        // ── Schema checks — mis en cache 24h (ne changent jamais en prod) ──
        $schemaFlags = Cache::remember("schema_flags_{$entrepriseId}", 86400, fn () => [
            'stocks' => schema_has_column('stocks', 'succursale_id'),
            'mouvement_stocks' => schema_has_column('mouvement_stocks', 'succursale_id'),
            'caisses' => schema_has_column('caisses', 'succursale_id'),
            'journals' => schema_has_column('journals', 'succursale_id'),
            'report_logs' => schema_has_column('report_logs', 'succursale_id'),
            'factures' => schema_has_column('factures', 'succursale_id'),
        ]);

        $hasSuccursales = Cache::remember("has_succursales_{$entrepriseId}", 300,
            fn () => Succursale::where('entreprise_id', $entrepriseId)->exists()
        );

        // ── KPIs — SUM en SQL, mis en cache 2 min ────────────────────────
        $kpiKey = "dashboard.kpis.{$entrepriseId}.".($succursaleId ?? 'all');
        [$totalStock, $totalVentes, $totalDepenses, $pendingFactures] = Cache::remember(
            $kpiKey, 120,
            function () use ($entrepriseId, $succursaleId, $schemaFlags) {
                $stock = Stock::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $schemaFlags['stocks'],
                        fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->selectRaw('SUM(quantite * COALESCE(prix_vente, 0)) as total')
                    ->value('total') ?? 0;

                $ventes = MouvementStock::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $schemaFlags['mouvement_stocks'],
                        fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('type', 'sortie')
                    ->sum(DB::raw('quantite * prix_unitaire'));

                $depenses = Caisse::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $schemaFlags['caisses'],
                        fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->sum('sortie');

                $pending = Facture::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $schemaFlags['factures'],
                        fn ($q) => $q->where('succursale_id', $succursaleId))
                    ->where('statut', 'en_attente')
                    ->count();

                return [$stock, $ventes, $depenses, $pending];
            }
        );

        // ── Activités récentes — eager load succursales en une requête ────
        $succursalesMap = $hasSuccursales
            ? Succursale::where('entreprise_id', $entrepriseId)
                ->pluck('nom', 'id')
            : collect();

        $journals = Journal::with('user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $schemaFlags['journals'],
                fn ($q) => $q->where('succursale_id', $succursaleId))
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
            ->map(function ($j) use ($succursaleId, $schemaFlags, $succursalesMap) {
                $description = 'Journal: '.($j->description ?: ucfirst($j->type));
                if (! $succursaleId && $schemaFlags['journals'] && $j->succursale_id) {
                    $nomSucc = $succursalesMap[$j->succursale_id] ?? null;
                    if ($nomSucc) {
                        $description = "[{$nomSucc}] ".$description;
                    }
                }

                return [
                    'id' => 'j-'.$j->id,
                    'description' => $description,
                    'type' => match ($j->type) {
                        'entree' => 'Entrée',
                        'sortie' => 'Sortie',
                        default => 'Journal',
                    },
                    'time' => $j->dateHeure_operation,
                    'occurred_at' => $j->dateHeure_operation,
                    'user' => $j->user->name ?? $user->name ?? 'Inconnu',
                ];
            });

        $reportLogs = ReportLog::with('user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $schemaFlags['report_logs'],
                fn ($q) => $q->where('succursale_id', $succursaleId))
            ->latest()->take(10)->get()
            ->map(function ($log) {
                $label = match ($log->report_type) {
                    'facture' => 'Facture',
                    'bon_entree' => "Bon d'entrée",
                    'journal' => 'Journal',
                    'mouvement_stock' => 'Mouvement stock',
                    default => ucfirst((string) $log->report_type),
                };

                return [
                    'id' => 'r-'.$log->id,
                    'description' => "{$label} ".($log->action === 'download' ? 'téléchargé' : 'imprimé'),
                    'type' => 'Document',
                    'time' => $log->created_at,
                    'occurred_at' => $log->created_at,
                    'user' => $log->user?->name ?? 'Inconnu',
                ];
            });

        $recentActivities = $journals->toBase()
            ->merge($reportLogs->toBase())
            ->sortByDesc(fn ($a) => $a['time'])
            ->take(10)->values()
            ->map(function ($a) {
                $time = is_string($a['time']) ? Carbon::parse($a['time']) : $a['time'];
                if ($time->greaterThan(Carbon::now())) {
                    $time = Carbon::now();
                }
                $a['occurred_at'] = $time->toDateTimeString();

                return $a;
            });

        // ── Top produits — GROUP BY SQL ───────────────────────────────────
        $topProduits = MouvementStock::with('produit')
            ->select('produit_id',
                DB::raw('SUM(quantite) as total_quantite'),
                DB::raw('SUM(quantite * prix_unitaire) as total_montant'))
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $schemaFlags['mouvement_stocks'],
                fn ($q) => $q->where('succursale_id', $succursaleId))
            ->where('type', 'sortie')
            ->groupBy('produit_id')
            ->orderByDesc('total_quantite')
            ->take(10)
            ->get()
            ->map(fn ($m) => [
                'nom' => $m->produit?->nom ?? 'Produit',
                'quantite' => (float) $m->total_quantite,
                'montant' => (float) $m->total_montant,
            ]);

        // ── Paramètres — réutilise le cache d'HandleInertiaRequests ─────────
        $parametres = Cache::remember("inertia.parametres.{$entrepriseId}", 300,
            fn () => Parametre::where('entreprise_id', $entrepriseId)->first()
        );
        $devise = $parametres?->devise ?? 'CDF';
        $entrepriseName = $parametres?->nom_entreprise ?? $user->entreprise?->name ?? 'Entreprise';
        $langue = $parametres?->langue ?? 'fr';
        Carbon::setLocale($langue);

        // ── Alertes stock ─────────────────────────────────────────────────
        if (! $succursaleId && $schemaFlags['stocks']) {
            $alertesStock = Stock::with(['produit', 'succursale'])
                ->where('entreprise_id', $entrepriseId)
                ->where('seuil_stock', '>', 0)
                ->whereColumn('quantite', '<=', 'seuil_stock')
                ->get()
                ->map(fn ($s) => [
                    'produit' => $s->produit?->nom ?? 'Produit',
                    'succursale' => $s->succursale?->nom ?? 'Central',
                    'quantite' => $s->quantite,
                    'seuil' => $s->seuil_stock,
                ])->values();
        } else {
            $alertesStock = Stock::with('produit')
                ->where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $schemaFlags['stocks'],
                    fn ($q) => $q->where('succursale_id', $succursaleId))
                ->where('seuil_stock', '>', 0)
                ->whereColumn('quantite', '<=', 'seuil_stock')
                ->get()
                ->map(fn ($s) => [
                    'produit' => $s->produit?->nom ?? 'Produit',
                    'succursale' => null,
                    'quantite' => $s->quantite,
                    'seuil' => $s->seuil_stock,
                ])->values();
        }

        // ── Graphiques — GROUP BY SQL, mis en cache 5 min ────────────────
        $year = Carbon::now()->year;
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $dbDriver = DB::connection()->getDriverName();

        $monthExpression = match ($dbDriver) {
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            default => 'MONTH(created_at)',
        };

        $dayExpression = match ($dbDriver) {
            'sqlite' => "CAST(strftime('%d', created_at) AS INTEGER)",
            default => 'DAY(created_at)',
        };

        $chartKey = "dashboard.charts.{$entrepriseId}.".($succursaleId ?? 'all').".{$year}.".Carbon::now()->format('Ym');
        [$monthlyChart, $dailyChart] = Cache::remember($chartKey, 300, function () use (
            $entrepriseId, $succursaleId, $schemaFlags, $year, $monthStart, $monthEnd,
            $monthExpression, $dayExpression
        ) {
            $monthlyData = MouvementStock::selectRaw("{$monthExpression} as mois, type, SUM(quantite * prix_unitaire) as total")
                ->where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $schemaFlags['mouvement_stocks'],
                    fn ($q) => $q->where('succursale_id', $succursaleId))
                ->whereYear('created_at', $year)
                ->whereIn('type', ['sortie', 'entree'])
                ->groupBy('mois', 'type')
                ->get()
                ->groupBy('type');

            $salesByMonth = $monthlyData->get('sortie', collect())->pluck('total', 'mois');
            $purchasesByMonth = $monthlyData->get('entree', collect())->pluck('total', 'mois');

            $months = collect(range(1, 12));
            $monthly = [
                'labels' => $months->map(fn ($m) => Carbon::create($year, $m, 1)->translatedFormat('M'))->values(),
                'sales' => $months->map(fn ($m) => (float) ($salesByMonth[$m] ?? 0))->values(),
                'purchases' => $months->map(fn ($m) => (float) ($purchasesByMonth[$m] ?? 0))->values(),
                'year' => $year,
            ];

            $dailyData = MouvementStock::selectRaw("{$dayExpression} as jour, type, SUM(quantite * prix_unitaire) as total")
                ->where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $schemaFlags['mouvement_stocks'],
                    fn ($q) => $q->where('succursale_id', $succursaleId))
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->whereIn('type', ['sortie', 'entree'])
                ->groupBy('jour', 'type')
                ->get()
                ->groupBy('type');

            $salesByDay = $dailyData->get('sortie', collect())->pluck('total', 'jour');
            $purchasesByDay = $dailyData->get('entree', collect())->pluck('total', 'jour');

            $daysInMonth = (int) $monthEnd->format('j');
            $days = collect(range(1, $daysInMonth));
            $daily = [
                'labels' => $days->map(fn ($d) => str_pad($d, 2, '0', STR_PAD_LEFT))->values(),
                'sales' => $days->map(fn ($d) => (float) ($salesByDay[$d] ?? 0))->values(),
                'purchases' => $days->map(fn ($d) => (float) ($purchasesByDay[$d] ?? 0))->values(),
                'month' => $monthStart->translatedFormat('F'),
                'year' => $monthStart->year,
            ];

            return [$monthly, $daily];
        });

        // ── Succursale active ─────────────────────────────────────────────
        $succursaleName = $succursaleId
            ? ($succursalesMap[$succursaleId] ?? null)
            : null;

        // ── Diagramme succursales ─────────────────────────────────────────
        $succursaleChart = null;
        if (! $succursaleId && $hasSuccursales && $schemaFlags['mouvement_stocks']) {
            $rows = MouvementStock::select('succursale_id',
                DB::raw('SUM(quantite * prix_unitaire) as total'))
                ->where('entreprise_id', $entrepriseId)
                ->whereNotNull('succursale_id')
                ->where('type', 'sortie')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->groupBy('succursale_id')
                ->get();

            if ($rows->isNotEmpty()) {
                $succursaleChart = [
                    'labels' => $rows->map(fn ($r) => $succursalesMap[$r->succursale_id] ?? "Succursale #{$r->succursale_id}")->values(),
                    'values' => $rows->map(fn ($r) => (float) $r->total)->values(),
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'totalStock' => (float) $totalStock,
            'totalVentes' => (float) $totalVentes,
            'totalDepenses' => (float) $totalDepenses,
            'pendingFactures' => $pendingFactures,
            'authUser' => $user,
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
