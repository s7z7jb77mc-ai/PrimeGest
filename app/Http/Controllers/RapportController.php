<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\MouvementStockArchive;
use App\Models\Journal;
use App\Models\Caisse;
use App\Models\Parametre;
use App\Models\Creance;
use App\Models\Dette;
use App\Models\Facture;
use App\Models\BonEntree;
use App\Models\ReportLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Afficher la page principale des rapports
     */
    public function index(Request $request)
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $hasMouvements = schema_has_column('mouvement_stocks', 'succursale_id');
        $hasJournals = schema_has_column('journals', 'succursale_id');
        $hasFactures = schema_has_column('factures', 'succursale_id');
        $hasBons = schema_has_column('bon_entrees', 'succursale_id');
        $hasCreances = schema_has_column('creances', 'succursale_id');
        $hasDettes = schema_has_column('dettes', 'succursale_id');
        $type = $request->input('type', 'journalier');
        $date = $request->input('date', now()->toDateString());
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');

        // Récupérer les paramètres de l'entreprise
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres ? $parametres->devise : 'CDF';

        // Générer le rapport selon le type
        $rapport = match($type) {
            'hebdomadaire' => $this->genererRapportHebdomadaire($entrepriseId, $dateDebut ?: $date, $dateFin, $devise, $succursaleId, $hasMouvements, $hasJournals, $hasFactures, $hasBons, $hasCreances, $hasDettes),
            'mensuel' => $this->genererRapportMensuel($entrepriseId, $date, $devise, $succursaleId, $hasMouvements, $hasJournals, $hasFactures, $hasBons, $hasCreances, $hasDettes),
            'annuel' => $this->genererRapportAnnuel($entrepriseId, $date, $devise, $succursaleId, $hasMouvements, $hasJournals, $hasFactures, $hasBons, $hasCreances, $hasDettes),
            default => $this->genererRapportJournalier($entrepriseId, $date, $devise, $succursaleId, $hasMouvements, $hasJournals, $hasFactures, $hasBons, $hasCreances, $hasDettes),
        };

        return Inertia::render('Rapport/Index', [
            'rapport' => $rapport,
            'type' => $type,
            'date' => $date,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'devise' => $devise,
            'parametres' => $parametres,
            'reportLogs' => ReportLog::with('user')
                ->where('entreprise_id', $entrepriseId)
                ->when($succursaleId && schema_has_column('report_logs', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    /**
     * Générer un rapport journalier
     */
    private function genererRapportJournalier($entrepriseId, $dateStr, $devise = 'CDF', ?int $succursaleId = null, bool $hasMouvements = false, bool $hasJournals = false, bool $hasFactures = false, bool $hasBons = false, bool $hasCreances = false, bool $hasDettes = false)
    {
        $date = Carbon::parse($dateStr)->toDateString();
        $periodeDebut = Carbon::parse($dateStr)->startOfDay();
        $periodeFin = Carbon::parse($dateStr)->endOfDay();

        // Mouvements du jour
        $mouvements = MouvementStock::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('created_at', $date)
            ->get();

        // Journaux du jour
        $journaux = Journal::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasJournals, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('dateHeure_operation', $date)
            ->get();

        // Calculer le stock initial (avant la date)
        $stockInitial = $this->calculerStockInitial($entrepriseId, $date, $succursaleId);

        $journauxDepenses = $this->filtrerDepenses($journaux);

        $creancesJour = Facture::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasFactures, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('statut', 'en_attente')
            ->whereDate('date_facture', $date)
            ->get()
            ->map(fn($f) => [
                'client' => $f->client_nom ?? $f->client?->nom_client ?? 'Client',
                'montant' => (float) ($f->total_ttc ?? $f->total_montant ?? 0),
                'date' => $f->date_facture ?? $f->created_at,
                'type' => 'Vente à crédit',
            ]);

        $dettesJour = BonEntree::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasBons, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('payment_type', 'credit')
            ->whereDate('date_bon', $date)
            ->get()
            ->map(fn($b) => [
                'fournisseur' => $b->fournisseur?->nom_entreprise_fournisseur ?? 'Fournisseur',
                'montant' => (float) ($b->total_montant ?? 0),
                'date' => $b->date_bon ?? $b->created_at,
                'type' => 'Achat à crédit',
            ]);

        $paiementsCreances = Creance::with('client')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasCreances, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('created_at', $date)
            ->get()
            ->map(fn($c) => [
                'client' => $c->client?->nom_client ?? 'Client',
                'montant' => (float) $c->montant_paye,
                'date' => $c->created_at,
                'type' => 'Paiement créance',
            ]);

        $paiementsDettes = Dette::with('fournisseur')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasDettes, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('created_at', $date)
            ->get()
            ->map(fn($d) => [
                'fournisseur' => $d->fournisseur?->nom_entreprise_fournisseur ?? 'Fournisseur',
                'montant' => (float) $d->montant_paye,
                'date' => $d->created_at,
                'type' => 'Paiement dette',
            ]);

        return [
            'mouvements' => $this->formaterMouvements($mouvements),
            'depenses' => $this->formaterDepenses($journauxDepenses),
            'resume' => $this->calculerResume($mouvements, $journauxDepenses, $stockInitial),
            'caisse' => $this->calculerCaisseResume($entrepriseId, $periodeDebut, $periodeFin, $succursaleId),
            'totalDepenses' => $journauxDepenses->sum('montant'),
            'periode' => $date,
            'periode_detaillee' => 'Rapport du ' . Carbon::parse($date)->format('d/m/Y'),
            'stock_initial' => $stockInitial,
            'devise' => $devise,
            'creances_jour' => $creancesJour,
            'dettes_jour' => $dettesJour,
            'paiements_creances' => $paiementsCreances,
            'paiements_dettes' => $paiementsDettes,
        ];
    }

    /**
     * Générer un rapport hebdomadaire
     */
    private function genererRapportHebdomadaire($entrepriseId, $dateDebut, $dateFin = null, $devise = 'CDF', ?int $succursaleId = null, bool $hasMouvements = false, bool $hasJournals = false, bool $hasFactures = false, bool $hasBons = false, bool $hasCreances = false, bool $hasDettes = false)
    {
        if ($dateFin) {
            // Période personnalisée
            $debut = Carbon::parse($dateDebut);
            $fin = Carbon::parse($dateFin);
        } else {
            // Logique par défaut basée sur une date (semaine complète)
            $date = Carbon::parse($dateDebut);
            $debut = $date->copy()->startOfWeek();
            $fin = $date->copy()->endOfWeek();
        }

        $mouvements = MouvementStock::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('created_at', [$debut, $fin])
            ->get();

        $journaux = Journal::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasJournals, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('dateHeure_operation', [$debut, $fin])
            ->get();

        // Calculer le stock initial (avant le début de la période)
        $stockInitial = $this->calculerStockInitial($entrepriseId, $debut->toDateString(), $succursaleId);

        $journauxDepenses = $this->filtrerDepenses($journaux);

        return [
            'mouvements' => $this->formaterMouvements($mouvements),
            'depenses' => $this->formaterDepenses($journauxDepenses),
            'resume' => $this->calculerResume($mouvements, $journauxDepenses, $stockInitial),
            'caisse' => $this->calculerCaisseResume($entrepriseId, $debut->copy()->startOfDay(), $fin->copy()->endOfDay(), $succursaleId),
            'totalDepenses' => $journauxDepenses->sum('montant'),
            'periode' => $debut->format('Y-m-d') . ' à ' . $fin->format('Y-m-d'),
            'periode_detaillee' => 'Rapport hebdomadaire du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
            'stock_initial' => $stockInitial,
            'devise' => $devise,
        ];
    }

    /**
     * Générer un rapport mensuel
     */
    private function genererRapportMensuel($entrepriseId, $dateStr, $devise = 'CDF', ?int $succursaleId = null, bool $hasMouvements = false, bool $hasJournals = false, bool $hasFactures = false, bool $hasBons = false, bool $hasCreances = false, bool $hasDettes = false)
    {
        $date = Carbon::parse($dateStr);
        $debut = $date->copy()->startOfMonth();
        $fin = $date->copy()->endOfMonth();

        $mouvements = MouvementStock::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('created_at', [$debut, $fin])
            ->get();

        $journaux = Journal::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasJournals, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('dateHeure_operation', [$debut, $fin])
            ->get();

        // Calculer le stock initial (avant le début du mois)
        $stockInitial = $this->calculerStockInitial($entrepriseId, $debut->toDateString(), $succursaleId);

        $journauxDepenses = $this->filtrerDepenses($journaux);

        return [
            'mouvements' => $this->formaterMouvements($mouvements),
            'depenses' => $this->formaterDepenses($journauxDepenses),
            'resume' => $this->calculerResume($mouvements, $journauxDepenses, $stockInitial),
            'caisse' => $this->calculerCaisseResume($entrepriseId, $debut->copy()->startOfDay(), $fin->copy()->endOfDay(), $succursaleId),
            'totalDepenses' => $journauxDepenses->sum('montant'),
            'periode' => $date->format('F Y'),
            'periode_detaillee' => 'Rapport mensuel de ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
            'stock_initial' => $stockInitial,
            'devise' => $devise,
        ];
    }

    /**
     * Générer un rapport annuel
     */
    private function genererRapportAnnuel($entrepriseId, $dateStr, $devise = 'CDF', ?int $succursaleId = null, bool $hasMouvements = false, bool $hasJournals = false, bool $hasFactures = false, bool $hasBons = false, bool $hasCreances = false, bool $hasDettes = false)
    {
        $date = Carbon::parse($dateStr);
        $debut = $date->copy()->startOfYear();
        $fin = $date->copy()->endOfYear();

        $mouvements = MouvementStock::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('created_at', [$debut, $fin])
            ->get();

        $journaux = Journal::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasJournals, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween('dateHeure_operation', [$debut, $fin])
            ->get();

        // Calculer le stock initial (avant le début de l'année)
        $stockInitial = $this->calculerStockInitial($entrepriseId, $debut->toDateString(), $succursaleId);

        $journauxDepenses = $this->filtrerDepenses($journaux);

        return [
            'mouvements' => $this->formaterMouvements($mouvements),
            'depenses' => $this->formaterDepenses($journauxDepenses),
            'resume' => $this->calculerResume($mouvements, $journauxDepenses, $stockInitial),
            'caisse' => $this->calculerCaisseResume($entrepriseId, $debut->copy()->startOfDay(), $fin->copy()->endOfDay(), $succursaleId),
            'totalDepenses' => $journauxDepenses->sum('montant'),
            'periode' => $date->format('Y'),
            'periode_detaillee' => 'Rapport annuel du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
            'stock_initial' => $stockInitial,
            'devise' => $devise,
        ];
    }

    /**
     * Formater les mouvements pour l'affichage
     */
    private function formaterMouvements($mouvements)
    {
        $groupes = [];

        foreach ($mouvements as $mouvement) {
            $produitId = $mouvement->produit_id;

            if (!isset($groupes[$produitId])) {
                $groupes[$produitId] = [
                    'produit_id' => $produitId,
                    'produit_nom' => $mouvement->produit->nom ?? 'N/A',
                    'prix_unitaire' => $mouvement->prix_unitaire,
                    'entrees' => 0,
                    'sorties' => 0,
                    'montant_entrees' => 0,
                    'montant_sorties' => 0,
                    'operations' => [],
                ];
            }

            if ($mouvement->type === 'entree') {
                $groupes[$produitId]['entrees'] += $mouvement->quantite;
                $groupes[$produitId]['montant_entrees'] += $mouvement->quantite * $mouvement->prix_unitaire;
            } else {
                $groupes[$produitId]['sorties'] += $mouvement->quantite;
                $groupes[$produitId]['montant_sorties'] += $mouvement->quantite * $mouvement->prix_unitaire;
            }

            // Format l'heure de manière sûre
            $heure = 'N/A';
            if ($mouvement->created_at instanceof \DateTime) {
                $heure = $mouvement->created_at->format('H:i:s');
            } elseif (is_string($mouvement->created_at)) {
                try {
                    $heure = Carbon::parse($mouvement->created_at)->format('H:i:s');
                } catch (\Exception $e) {
                    $heure = 'N/A';
                }
            }

            $groupes[$produitId]['operations'][] = [
                'type' => $mouvement->type,
                'quantite' => $mouvement->quantite,
                'montant' => $mouvement->quantite * $mouvement->prix_unitaire,
                'user' => $mouvement->user ? $mouvement->user->name : 'N/A',
                'heure' => $heure,
            ];
        }

        return array_values($groupes);
    }

    /**
     * Formater les dépenses pour l'affichage
     */
    private function formaterDepenses($journaux)
    {
        return $journaux->map(function ($journal) {
            // Format l'heure de manière sûre
            $heure = 'N/A';
            if ($journal->dateHeure_operation instanceof \DateTime) {
                $heure = $journal->dateHeure_operation->format('H:i:s');
            } elseif (is_string($journal->dateHeure_operation)) {
                try {
                    $heure = Carbon::parse($journal->dateHeure_operation)->format('H:i:s');
                } catch (\Exception $e) {
                    $heure = 'N/A';
                }
            }

            return [
                'id' => $journal->id,
                'libelle' => $journal->description,
                'montant' => $journal->montant,
                'user' => $journal->user?->name ?? 'N/A',
                'heure' => $heure,
            ];
        })->toArray();
    }

    /**
     * Filtrer les dépenses à partir du journal (sorties uniquement, hors achats de stock)
     */
    private function filtrerDepenses($journaux)
    {
        return $journaux->filter(function ($journal) {
            $description = $journal->description ?? '';
            if ($journal->type !== 'sortie') {
                return false;
            }
            if (stripos($description, 'Achat') === 0) {
                return false;
            }
            if (stripos($description, 'réduction') !== false || stripos($description, 'reduction') !== false) {
                return false;
            }
            return true;
        })->values();
    }

    /**
     * Calculer le résumé financier
     */
    private function calculerResume($mouvements, $journaux, $stockInitial = ['valeur' => 0, 'quantite' => 0])
    {
        $entrees = $mouvements->where('type', 'entree')->sum(fn($m) => $m->quantite * $m->prix_unitaire);
        $sorties = $mouvements->where('type', 'sortie')->sum(fn($m) => $m->quantite * $m->prix_unitaire);

        return [
            'stock_initial_valeur' => $stockInitial['valeur'],
            'stock_initial_quantite' => $stockInitial['quantite'],
            'total_entrees' => $entrees,
            'total_sorties' => $sorties,
            'total_depenses' => $journaux->sum('montant'),
            'stock_final_valeur' => $stockInitial['valeur'] + $entrees - $sorties,
            'stock_final_quantite' => $stockInitial['quantite'] + $mouvements->where('type', 'entree')->sum('quantite') - $mouvements->where('type', 'sortie')->sum('quantite'),
            'difference' => $entrees - $sorties,
            'recette' => $sorties,
            'explications' => [
                'stock_final_valeur' => 'Stock final = Stock initial + Entrées - Sorties',
                'difference' => 'Différence = Entrées - Sorties',
                'recette' => 'Recette = Valeur des sorties (ventes)',
            ],
        ];
    }

    /**
     * Calculer le stock initial avant une date donnée
     */
    private function calculerStockInitial($entrepriseId, $date, ?int $succursaleId = null)
    {
        $cutoff = Carbon::parse($date)->startOfDay();
        $stocks = Stock::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('stocks', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->get();

        $mouvementsApres = MouvementStock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('mouvement_stocks', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('created_at', '>=', $cutoff)
            ->get()
            ->groupBy('produit_id');

        $stockParProduit = [];
        foreach ($stocks as $s) {
            $after = $mouvementsApres->get($s->produit_id, collect());
            $entrees = $after->where('type', 'entree')->sum('quantite');
            $sorties = $after->where('type', 'sortie')->sum('quantite');
            $quantiteInitiale = (float) $s->quantite - (float) $entrees + (float) $sorties;
            if ($quantiteInitiale < 0) {
                $quantiteInitiale = 0;
            }
            $stockParProduit[$s->produit_id] = [
                'quantite' => (float) $quantiteInitiale,
                'valeur' => (float) $quantiteInitiale * (float) ($s->prix_vente ?? 0),
                'prix_moyen' => (float) ($s->prix_vente ?? 0),
            ];
        }

        $totalQuantite = collect($stockParProduit)->sum('quantite');
        $totalValeur = collect($stockParProduit)->sum('valeur');

        return [
            'quantite' => max(0, $totalQuantite),
            'valeur' => max(0, $totalValeur),
            'par_produit' => $stockParProduit,
        ];
    }

    /**
     * Calculer le résumé de la caisse sur une période
     */
    private function calculerCaisseResume($entrepriseId, Carbon $debut, Carbon $fin, ?int $succursaleId = null)
    {
        $initial = null;
        if (schema_has_column('caisses', 'type_operation')) {
            $initial = Caisse::where('entreprise_id', $entrepriseId)
                ->when($succursaleId && schema_has_column('caisses', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
                ->where('type_operation', 'initial')
                ->orderBy('created_at')
                ->first();
        }

        $dateField = DB::raw('COALESCE(date_operation, created_at)');

        $entreesPeriode = Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('caisses', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween($dateField, [$debut, $fin])
            ->sum('entree');

        $sortiesPeriode = Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('caisses', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereBetween($dateField, [$debut, $fin])
            ->sum('sortie');

        $entreesTotal = Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('caisses', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->sum('entree');
        $sortiesTotal = Caisse::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('caisses', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->sum('sortie');

        return [
            'has_initial' => (bool) $initial,
            'initial_montant' => $initial?->entree ?? 0,
            'initial_date' => $initial?->date_operation?->toDateString(),
            'entrees_periode' => $entreesPeriode,
            'sorties_periode' => $sortiesPeriode,
            'solde_periode' => $entreesPeriode - $sortiesPeriode,
            'entrees_total' => $entreesTotal,
            'sorties_total' => $sortiesTotal,
            'solde_total' => $entreesTotal - $sortiesTotal,
        ];
    }

    public function logAction(Request $request)
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $validated = $request->validate([
            'action' => 'required|in:download,print',
            'report_type' => 'required|string|max:20',
            'report_date' => 'nullable|string|max:50',
        ]);

        $payload = [
            'entreprise_id' => $entrepriseId,
            'user_id' => Auth::id(),
            'action' => $validated['action'],
            'report_type' => $validated['report_type'],
            'report_date' => $validated['report_date'] ?? null,
        ];
        if (schema_has_column('report_logs', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        ReportLog::create($payload);

        return response()->json(['status' => 'ok']);
    }
}
