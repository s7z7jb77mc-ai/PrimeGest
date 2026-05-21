<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Succursale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
//use App\Support\SuccursaleContext;

class ArchiveController extends Controller
{
    // ----------------------------------------------------------------
    // Colonnes dynamiques selon le schéma
    // ----------------------------------------------------------------

    private function archiveColumns(): array
    {
        $cols = ['id', 'date_archive', 'reference_id'];
        if (schema_has_column('archives', 'succursale_id')) {
            $cols[] = 'succursale_id';
        }
        return $cols;
    }

    // ----------------------------------------------------------------
    // Query de base
    // ✅ withoutGlobalScopes() contourne HasSuccursaleScope qui sinon
    //    ignore notre filtre manuel et laisse passer toutes les archives.
    // ✅ Succursale active → filtre sur succursale_id
    // ✅ Dashboard central → toutes les archives de l'entreprise
    // ----------------------------------------------------------------

    private function baseQuery(string $type): \Illuminate\Database\Eloquent\Builder
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasSuccCol   = schema_has_column('archives', 'succursale_id');

    //   dd([
    //'succursale_id_session' => session('succursale_id'),
    //'sql_complet' => Archive::withoutGlobalScopes()
      //  ->where('entreprise_id', auth()->user()->entreprise_id)
        //->when(
          //  schema_has_column('archives', 'succursale_id') && session('succursale_id'),
            //fn($q) => $q->where('succursale_id', session('succursale_id'))
        //)
        //->where('type', 'journal')
        //->toSql(),
    //'has_succursale_col' => schema_has_column('archives', 'succursale_id'),
//]);

        return Archive::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when(
                $hasSuccCol && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->where('type', $type);
    }

    // ----------------------------------------------------------------
    // Index — vue par année
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasSuccCol   = schema_has_column('archives', 'succursale_id');
        $type         = $request->input('type', 'journal');
        $year         = $request->input('year', now()->year);

        $query = $this->baseQuery($type)
            ->whereYear('date_archive', $year)
            ->orderBy('date_archive', 'desc');

        // ✅ Transferts au central : dédupliquer par reference_id
        // (deux archives créées par transfert : from + to)
        if ($type === 'transfert' && !$succursaleId && $hasSuccCol) {
            $query->whereIn('id', function ($sub) use ($entrepriseId, $type, $year) {
                $sub->selectRaw('MAX(id)')
                    ->from('archives')
                    ->where('entreprise_id', $entrepriseId)
                    ->where('type', $type)
                    ->whereYear('date_archive', $year)
                    ->groupBy('reference_id');
            });
        }

        $archives = $query->get($this->archiveColumns());

        $months = collect(range(1, 12))->mapWithKeys(function ($m) use ($archives) {
            $count = $archives->filter(fn($a) => (int) $a->date_archive->format('m') === $m)->count();
            return [$m => $count];
        });

        $years = Archive::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when($hasSuccCol && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
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
            'type'   => $type,
            'year'   => (int) $year,
            'years'  => $years,
            'months' => $months,
        ]);
    }

    // ----------------------------------------------------------------
    // Vue par mois
    // ----------------------------------------------------------------

    public function month(Request $request, string $type, int $year, int $month)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasSuccCol   = schema_has_column('archives', 'succursale_id');

        $query = $this->baseQuery($type)
            ->whereYear('date_archive', $year)
            ->whereMonth('date_archive', $month)
            ->orderBy('date_archive', 'desc');

        if ($type === 'transfert' && !$succursaleId && $hasSuccCol) {
            $query->whereIn('id', function ($sub) use ($entrepriseId, $type, $year, $month) {
                $sub->selectRaw('MAX(id)')
                    ->from('archives')
                    ->where('entreprise_id', $entrepriseId)
                    ->where('type', $type)
                    ->whereYear('date_archive', $year)
                    ->whereMonth('date_archive', $month)
                    ->groupBy('reference_id');
            });
        }

        $archives = $query->get($this->archiveColumns());

        $days = $archives->groupBy(fn($a) => $a->date_archive->format('Y-m-d'))
            ->map(fn($items) => $items->count())
            ->toArray();

        return Inertia::render('Archives/Month', [
            'type'  => $type,
            'year'  => $year,
            'month' => $month,
            'days'  => $days,
        ]);
    }

    // ----------------------------------------------------------------
    // Vue par jour
    // ----------------------------------------------------------------

    public function day(Request $request, string $type, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasSuccCol   = schema_has_column('archives', 'succursale_id');
        $dateObj      = Carbon::parse($date)->toDateString();

        $query = $this->baseQuery($type)
            ->whereDate('date_archive', $dateObj)
            ->orderByDesc('id');

        if ($type === 'transfert' && !$succursaleId && $hasSuccCol) {
            $query->whereIn('id', function ($sub) use ($entrepriseId, $type, $dateObj) {
                $sub->selectRaw('MAX(id)')
                    ->from('archives')
                    ->where('entreprise_id', $entrepriseId)
                    ->where('type', $type)
                    ->whereDate('date_archive', $dateObj)
                    ->groupBy('reference_id');
            });
        }

        $archives          = $query->get();
        $archivesFormatted = $this->formatArchives($type, $archives, $entrepriseId, $succursaleId);

        return Inertia::render('Archives/Day', [
            'type'     => $type,
            'date'     => $dateObj,
            'archives' => $archivesFormatted,
        ]);
    }

    // ----------------------------------------------------------------
    // Téléchargement JSON
    // ----------------------------------------------------------------

    public function download(string $type, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasSuccCol   = schema_has_column('archives', 'succursale_id');
        $dateObj      = Carbon::parse($date)->toDateString();

        $query = $this->baseQuery($type)
            ->whereDate('date_archive', $dateObj)
            ->orderBy('id');

        if ($type === 'transfert' && !$succursaleId && $hasSuccCol) {
            $query->whereIn('id', function ($sub) use ($entrepriseId, $type, $dateObj) {
                $sub->selectRaw('MAX(id)')
                    ->from('archives')
                    ->where('entreprise_id', $entrepriseId)
                    ->where('type', $type)
                    ->whereDate('date_archive', $dateObj)
                    ->groupBy('reference_id');
            });
        }

        $archives          = $query->get();
        $archivesFormatted = $this->formatArchives($type, $archives, $entrepriseId, $succursaleId);
        $filename          = "archive_{$type}_{$dateObj}.json";
        $content           = json_encode($archivesFormatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn() => print($content), $filename, ['Content-Type' => 'application/json']);
    }

    // ----------------------------------------------------------------
    // Export PDF
    // ----------------------------------------------------------------

    public function pdf(string $type, string $date, Request $request)
    {
        if (!in_array($type, ['journal', 'mouvement_stock', 'bon_entree', 'caisse'])) {
            abort(404);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj      = Carbon::parse($date)->toDateString();
        $parametres   = \App\Models\Parametre::where('entreprise_id', $entrepriseId)->first();

        $archives  = $this->baseQuery($type)->whereDate('date_archive', $dateObj)->orderBy('id')->get();
        $formatted = $this->formatArchives($type, $archives, $entrepriseId, $succursaleId);

        if ($type === 'bon_entree') {
            $ids = collect(explode(',', (string) $request->query('ids')))->filter()->values();
            if ($ids->isNotEmpty()) {
                $formatted = $formatted->filter(fn($b) => in_array((string) ($b['reference_id'] ?? ''), $ids->all()))->values();
            }
        }

        $view = match ($type) {
            'journal'         => 'pdf.archives-journal',
            'mouvement_stock' => 'pdf.archives-mouvement-stock',
            'bon_entree'      => 'pdf.archives-bon-entree',
            'caisse'          => 'pdf.archives-caisse',
        };

        $pdf = app('dompdf.wrapper')->loadView($view, [
            'date'       => $dateObj,
            'archives'   => $formatted,
            'parametres' => $parametres,
        ]);

        return $request->query('inline') === '1'
            ? $pdf->stream("archives_{$type}_{$dateObj}.pdf")
            : $pdf->download("archives_{$type}_{$dateObj}.pdf");
    }

    // ----------------------------------------------------------------
    // PDF factures
    // ----------------------------------------------------------------

    public function facturesPdf(Request $request, string $date)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateObj      = Carbon::parse($date)->toDateString();
        $parametres   = \App\Models\Parametre::where('entreprise_id', $entrepriseId)->first();

        $archives  = $this->baseQuery('facture')->whereDate('date_archive', $dateObj)->orderBy('id')->get();
        $formatted = $this->formatArchives('facture', $archives, $entrepriseId, $succursaleId)->values();

        $ids = collect(explode(',', (string) $request->query('ids')))->filter()->values();
        if ($ids->isNotEmpty()) {
            $formatted = $formatted->filter(fn($f) => in_array((string) ($f['reference_id'] ?? ''), $ids->all()))->values();
        }

        $pdf = app('dompdf.wrapper')->loadView('pdf.archives-factures', [
            'date'          => $dateObj,
            'factures'      => $formatted,
            'logo_url'      => $parametres?->logo_url,
            'logo_position' => $parametres?->logo_position ?? 'left',
            'parametres'    => $parametres,
        ]);

        return $request->query('inline') === '1'
            ? $pdf->stream("archives_factures_{$dateObj}.pdf")
            : $pdf->download("archives_factures_{$dateObj}.pdf");
    }

    // ----------------------------------------------------------------
    // Formatage des archives
    // ----------------------------------------------------------------

    private function formatArchives(string $type, $archives, int $entrepriseId, ?int $succursaleId)
    {
        $hasSuccCol = schema_has_column('archives', 'succursale_id');

        // Charger les noms de succursales pour le préfixage au central
        $succursaleMap = [];
        if (!$succursaleId && $hasSuccCol) {
            $ids = $archives->pluck('succursale_id')->filter()->unique()->values();
            if ($ids->isNotEmpty()) {
                $succursaleMap = Succursale::withoutGlobalScopes()
                    ->whereIn('id', $ids)
                    ->where('entreprise_id', $entrepriseId)
                    ->pluck('nom', 'id')
                    ->toArray();
            }
        }

        $prefix = function ($archive, ?string $text) use ($succursaleMap, $hasSuccCol): ?string {
            if (!$text) return $text;
            $sid = $hasSuccCol ? ($archive->succursale_id ?? null) : null;
            if ($sid && isset($succursaleMap[$sid])) {
                $p = '[' . $succursaleMap[$sid] . '] ';
                return str_starts_with($text, $p) ? $text : $p . $text;
            }
            return $text;
        };

        if ($type === 'journal') {
            $userIds    = $archives->pluck('payload')->pluck('user_id')->filter()->unique()->values();
            $productIds = $archives->pluck('payload')->pluck('produit_id')->filter()->unique()->values();
            $users      = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');
            $products   = \App\Models\Produit::whereIn('id', $productIds)->pluck('nom', 'id');

            return $archives->map(function ($a) use ($users, $products, $prefix) {
                $p       = $a->payload ?? [];
                $dateVal = $p['dateHeure_operation'] ?? null;
                $dateObj = $dateVal ? Carbon::parse($dateVal) : null;
                return [
                    'date'        => $dateObj?->toDateString(),
                    'heure'       => $dateObj?->format('H:i:s'),
                    'type'        => $p['type'] ?? null,
                    'description' => $prefix($a, $p['description'] ?? null),
                    'montant'     => $p['montant'] ?? 0,
                    'user'        => $users[$p['user_id'] ?? 0] ?? 'N/A',
                    'produit'     => $products[$p['produit_id'] ?? 0] ?? 'N/A',
                ];
            });
        }

        if ($type === 'mouvement_stock') {
            $userIds    = $archives->pluck('payload')->pluck('user_id')->filter()->unique()->values();
            $productIds = $archives->pluck('payload')->pluck('produit_id')->filter()->unique()->values();
            $users      = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');
            $products   = \App\Models\Produit::whereIn('id', $productIds)->pluck('nom', 'id');

            return $archives->map(function ($a) use ($users, $products, $prefix) {
                $p = $a->payload ?? [];
                return [
                    'date'          => $p['created_at'] ?? null,
                    'type'          => $p['type'] ?? null,
                    'produit'       => $products[$p['produit_id'] ?? 0] ?? 'N/A',
                    'quantite'      => $p['quantite'] ?? 0,
                    'prix_unitaire' => $p['prix_unitaire'] ?? 0,
                    'prix_total'    => $p['prix_total'] ?? 0,
                    'commentaire'   => $prefix($a, $p['commentaire'] ?? null),
                    'user'          => $users[$p['user_id'] ?? 0] ?? 'N/A',
                ];
            });
        }

        if ($type === 'caisse') {
            return $archives->map(function ($a) use ($prefix) {
                $p = $a->payload ?? [];
                return [
                    'reference_id'   => $a->reference_id,
                    'date_operation' => $p['date_operation'] ?? null,
                    'description'    => $prefix($a, $p['description'] ?? null),
                    'entree'         => $p['entree'] ?? 0,
                    'sortie'         => $p['sortie'] ?? 0,
                    'solde'          => $p['solde'] ?? 0,
                    'type_operation' => $p['type_operation'] ?? null,
                ];
            })->sortByDesc(fn($r) => $r['date_operation'] ? strtotime((string) $r['date_operation']) : 0)->values();
        }

        if ($type === 'transfert') {
            return $archives->map(function ($a) {
                $p = $a->payload ?? [];
                return [
                    'reference_id'    => $a->reference_id,
                    'date_operation'  => $p['date_operation'] ?? null,
                    'type_transfert'  => $p['type'] ?? null,
                    'from_succursale' => $p['from_succursale'] ?? null,
                    'to_succursale'   => $p['to_succursale'] ?? null,
                    'produit'         => $p['produit'] ?? null,
                    'quantite'        => $p['quantite'] ?? null,
                    'montant'         => $p['montant'] ?? null,
                    'statut'          => $p['status'] ?? null,
                ];
            })->sortByDesc(fn($r) => $r['date_operation'] ? strtotime((string) $r['date_operation']) : 0)->values();
        }

        if ($type === 'bon_entree') {
            return $archives->map(function ($a) {
                $p = $a->payload ?? [];
                return [
                    'reference_id' => $a->reference_id,
                    'numero'       => $p['numero'] ?? null,
                    'date_bon'     => $p['date_bon'] ?? null,
                    'fournisseur'  => $p['fournisseur'] ?? null,
                    'total'        => $p['total'] ?? 0,
                    'lignes'       => $p['lignes'] ?? [],
                ];
            })->sortByDesc(fn($b) => $b['date_bon'] ? strtotime((string) $b['date_bon']) : 0)->values();
        }

        // facture
        return $archives->map(function ($a) {
            $p = $a->payload ?? [];
            return [
                'reference_id' => $a->reference_id,
                'numero'       => $p['numero'] ?? null,
                'date_facture' => $p['date_facture'] ?? null,
                'client'       => $p['client'] ?? null,
                'client_phone' => $p['client_phone'] ?? null,
                'total_ht'     => $p['total_ht'] ?? 0,
                'total_tva'    => $p['total_tva'] ?? 0,
                'total_ttc'    => $p['total_ttc'] ?? 0,
                'tva'          => $p['tva'] ?? 0,
                'statut'       => $p['statut'] ?? null,
                'lignes'       => $p['lignes'] ?? [],
            ];
        })->sortByDesc(fn($f) => $f['date_facture'] ? strtotime((string) $f['date_facture']) : 0)->values();
    }
}