<?php

namespace App\Http\Controllers;

use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Parametre;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Archive;
use App\Models\BonEntree;
use App\Models\BonEntreeLigne;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\ReductionUsage;
use App\Models\Succursale;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MouvementStockController extends Controller
{
    public function __construct(private MouvementStockService $mouvementStockService)
    {
    }

    // ----------------------------------------------------------------
    // Index
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $entrepriseId    = auth()->user()->entreprise_id;
        $succursaleId    = session('succursale_id');
        $hasStocks       = schema_has_column('stocks', 'succursale_id');
        $hasMouvements   = schema_has_column('mouvement_stocks', 'succursale_id');
        $hasClients      = schema_has_column('clients', 'succursale_id');
        $hasFournisseurs = schema_has_column('fournisseurs', 'succursale_id');

        // ── Aperçu stock ─────────────────────────────────────────────────
        // ✅ Dashboard central (pas de succursale active) :
        //    On consolide les quantités par produit (somme de toutes les succursales)
        //    pour éviter les doublons dans le tableau.
        // ✅ Dashboard succursale : on filtre sur la succursale active uniquement.
        if (!$succursaleId && $hasStocks) {
            // Central : regrouper par produit_id, sommer les quantités
            $stocksRaw = Stock::with('produit:id,nom,uuid')
                ->where('stocks.entreprise_id', $entrepriseId)
                ->join('produits', 'stocks.produit_id', '=', 'produits.id')
                ->orderBy('produits.nom')
                ->select(
                    'stocks.produit_id',
                    DB::raw('SUM(stocks.quantite) as quantite'),
                    DB::raw('MAX(stocks.prix_achat) as prix_achat'),
                    DB::raw('MAX(stocks.prix_vente) as prix_vente'),
                    DB::raw('SUM(stocks.total_achat) as total_achat'),
                    DB::raw('SUM(stocks.total_vente) as total_vente'),
                    DB::raw('MIN(stocks.seuil_stock) as seuil_stock')
                )
                ->groupBy('stocks.produit_id', 'produits.nom')
                ->get();

            // Charger les noms de succursales pour les alertes
            $succursaleMap = Succursale::where('entreprise_id', $entrepriseId)
                ->pluck('nom', 'id')->toArray();

            // Alertes stock avec nom de la succursale
            $alertesStock = Stock::with(['produit', 'succursale'])
                ->where('stocks.entreprise_id', $entrepriseId)
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

            $stocks = $stocksRaw;
        } else {
            // Succursale active : vue filtrée
            $stocks = Stock::with('produit:id,nom,uuid')
                ->where('stocks.entreprise_id', $entrepriseId)
                ->when($succursaleId && $hasStocks, fn($q) => $q->where('stocks.succursale_id', $succursaleId))
                ->join('produits', 'stocks.produit_id', '=', 'produits.id')
                ->orderBy('produits.nom')
                ->select('stocks.*')
                ->get();

            // Alertes stock filtrées sur la succursale
            $alertesStock = Stock::with('produit:id,nom')
                ->where('stocks.entreprise_id', $entrepriseId)
                ->when($succursaleId && $hasStocks, fn($q) => $q->where('stocks.succursale_id', $succursaleId))
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

        $today = now()->toDateString();
        $mouvements = MouvementStock::with([
                'produit:id,nom',
                'user:id,name',
            ])
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasMouvements, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('created_at', $today)
            ->latest()
            ->limit(200)
            ->get();

        $produits = Produit::where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->select(['id', 'uuid', 'nom', 'prix_achat', 'prix_vente'])
            ->get();

        $clients = Client::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasClients, fn($q) => $q->where('succursale_id', $succursaleId))
            ->orderBy('nom_client')
            ->select(['id', 'uuid', 'nom_client', 'numero_telephone', 'reduction_accordee'])
            ->get();

        $fournisseurs = Fournisseur::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && $hasFournisseurs, fn($q) => $q->where('succursale_id', $succursaleId))
            ->orderBy('nom_entreprise_fournisseur')
            ->select(['id', 'uuid', 'nom_entreprise_fournisseur', 'reduction_obtenue'])
            ->get();

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();

        return Inertia::render('MouvementStock/Index', [
            'stocks'       => $stocks,
            'alertesStock' => $alertesStock,
            'mouvements'   => $mouvements,
            'produits'     => $produits,
            'clients'      => $clients,
            'fournisseurs' => $fournisseurs,
            'parametres'   => $parametres,
        ]);
    }

    // ----------------------------------------------------------------
    // Store (mouvement simple)
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produit_id'     => 'required|exists:produits,id',
            'type'           => 'required|in:entree,sortie',
            'quantite'       => 'required|integer|min:1',
            'prix_unitaire'  => 'nullable|numeric|min:0',
            'commentaire'    => 'nullable|string|max:1000',
            'payment_type'   => 'nullable|in:cash,credit,reduction',
            'use_reduction'  => 'nullable|boolean',
            'client_phone'   => 'nullable|string|max:30',
            'fournisseur_id' => 'nullable|exists:fournisseurs,id',
        ]);

        $entrepriseId    = auth()->user()->entreprise_id;
        $succursaleId    = session('succursale_id');
        $parametres      = Parametre::where('entreprise_id', $entrepriseId)->first();
        $tauxReduction   = (float) ($parametres?->reduction_accordee ?? 0);
        $hasClients      = schema_has_column('clients', 'succursale_id');
        $hasFournisseurs = schema_has_column('fournisseurs', 'succursale_id');

        $produit = Produit::where('entreprise_id', $entrepriseId)->findOrFail($validated['produit_id']);

        DB::transaction(function () use ($validated, $entrepriseId, $produit, $tauxReduction, $succursaleId, $hasClients, $hasFournisseurs) {
            $useReduction       = (bool) ($validated['use_reduction'] ?? false);
            $paymentType        = $useReduction ? 'reduction' : ($validated['payment_type'] ?? 'cash');
            $prixUnitaireFinal  = $validated['prix_unitaire'] ?? ($validated['type'] === 'entree' ? $produit->prix_achat : $produit->prix_vente);
            $total              = (int) $validated['quantite'] * (float) $prixUnitaireFinal;
            $clientUpdated      = false;
            $fournisseurUpdated = false;
            $commentaire        = $validated['commentaire'] ?? null;

            if ($validated['type'] === 'sortie' && trim((string) $commentaire) === '') {
                $commentaire = 'Vente - ' . $produit->nom;
            }

            if ($useReduction && ($validated['payment_type'] ?? null) === 'credit') {
                throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
            }

            if ($useReduction && $validated['type'] === 'sortie') {
                $clientPhone = trim((string) ($validated['client_phone'] ?? ''));
                if ($clientPhone === '') throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour utiliser la réduction.']);
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (!$client) throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
                $reductionDisponible = (float) $client->reduction_accordee;
                $reductionUtilisee   = min($total, $reductionDisponible);
                $resteAPayer         = $total - $reductionUtilisee;
                $client->reduction_accordee = max($reductionDisponible - $reductionUtilisee, 0);
                $client->achat_mensuel = 0;
                $client->save();
                $clientUpdated = true;
                $this->recordReductionUsage($entrepriseId, 'client', $client->id, $reductionUtilisee, (float) $client->reduction_accordee);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Vente (réduction) : {$produit->nom}", 'date_operation' => now(), 'entree' => $resteAPayer, 'sortie' => 0];
                    if (schema_has_column('caisses', 'type_operation')) $caisseData['type_operation'] = 'auto';
                    CaisseService::createOperation($caisseData);
                }
            }

            if ($useReduction && $validated['type'] === 'entree') {
                $fournisseurId = $validated['fournisseur_id'] ?? null;
                if (!$fournisseurId) throw ValidationException::withMessages(['fournisseur_id' => 'Fournisseur requis pour utiliser la réduction.']);
                $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->findOrFail($fournisseurId);
                $reductionDisponible = (float) $fournisseur->reduction_obtenue;
                $reductionUtilisee   = min($total, $reductionDisponible);
                $resteAPayer         = $total - $reductionUtilisee;
                $fournisseur->reduction_obtenue = max($reductionDisponible - $reductionUtilisee, 0);
                $fournisseur->achat_mensuel = 0;
                $fournisseur->save();
                $fournisseurUpdated = true;
                $this->recordReductionUsage($entrepriseId, 'fournisseur', $fournisseur->id, $reductionUtilisee, (float) $fournisseur->reduction_obtenue);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Achat (réduction) : {$produit->nom}", 'date_operation' => now(), 'entree' => 0, 'sortie' => $resteAPayer];
                    if (schema_has_column('caisses', 'type_operation')) $caisseData['type_operation'] = 'auto';
                    CaisseService::createOperation($caisseData);
                }
            }

            if ($paymentType === 'credit' && $validated['type'] === 'sortie') {
                $clientPhone = trim((string) ($validated['client_phone'] ?? ''));
                if ($clientPhone === '') throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour une vente à crédit.']);
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (!$client) throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
                $client->creance = (float) $client->creance + $total;
                $this->updateClientStats($client, $total, $tauxReduction);
                $client->save();
                $clientUpdated = true;
            }

            if ($paymentType === 'credit' && $validated['type'] === 'entree') {
                $fournisseurId = $validated['fournisseur_id'] ?? null;
                if (!$fournisseurId) throw ValidationException::withMessages(['fournisseur_id' => 'Fournisseur requis pour un achat à crédit.']);
                $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->findOrFail($fournisseurId);
                $fournisseur->dette = (float) $fournisseur->dette + $total;
                $this->updateFournisseurStats($fournisseur, $total);
                $fournisseur->save();
                $fournisseurUpdated = true;
            }

            if ($validated['type'] === 'sortie' && !empty($validated['client_phone']) && !$clientUpdated) {
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', trim((string) $validated['client_phone']))->first();
                if ($client) {
                    if (!$useReduction) $this->updateClientStats($client, $total, $tauxReduction);
                    $client->save();
                }
            }

            if ($validated['type'] === 'entree' && !empty($validated['fournisseur_id']) && !$fournisseurUpdated) {
                $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasFournisseurs, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->find($validated['fournisseur_id']);
                if ($fournisseur) {
                    if (!$useReduction) $this->updateFournisseurStats($fournisseur, $total);
                    $fournisseur->save();
                }
            }

            $this->appliquerMouvementStock($entrepriseId, $produit, $validated['type'], (int) $validated['quantite'], $validated['prix_unitaire'], $commentaire, null, $paymentType);
        });

        return redirect()->route('mouvement-stocks.index');
    }

    // ----------------------------------------------------------------
    // Générer une facture de vente (plusieurs produits)
    // ----------------------------------------------------------------

    public function genererFactureVente(Request $request)
    {
        $validated = $request->validate([
            'lignes'                 => 'required|array|min:1',
            'lignes.*.produit_id'    => 'required|exists:produits,id',
            'lignes.*.quantite'      => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'nullable|numeric|min:0',
            'payment_type'           => 'nullable|in:cash,credit,reduction',
            'use_reduction'          => 'nullable|boolean',
            'client_phone'           => 'nullable|string|max:30',
        ]);

        $entrepriseId  = auth()->user()->entreprise_id;
        $succursaleId  = session('succursale_id');
        $userId        = auth()->id();
        $parametres    = Parametre::where('entreprise_id', $entrepriseId)->first();
        $tva           = (float) ($parametres?->tva ?? 0);
        $tauxReduction = (float) ($parametres?->reduction_accordee ?? 0);
        $hasClients    = schema_has_column('clients', 'succursale_id');

        $facture = DB::transaction(function () use ($validated, $entrepriseId, $succursaleId, $userId, $tva, $tauxReduction, $hasClients) {
            $totalTtc     = 0;
            $totalHt      = 0;
            $totalTva     = 0;
            $useReduction = (bool) ($validated['use_reduction'] ?? false);
            $paymentType  = $useReduction ? 'reduction' : ($validated['payment_type'] ?? 'cash');
            $client       = null;
            $clientPhone  = trim((string) ($validated['client_phone'] ?? ''));

            if ($useReduction && ($validated['payment_type'] ?? null) === 'credit') {
                throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
            }
            if ($useReduction && $clientPhone === '') throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour utiliser la réduction.']);
            if ($paymentType === 'credit' && $clientPhone === '') throw ValidationException::withMessages(['client_phone' => 'Numéro du client requis pour une vente à crédit.']);
            if ($clientPhone !== '') {
                $client = Client::where('entreprise_id', $entrepriseId)
                    ->when($succursaleId && $hasClients, fn($q) => $q->where('succursale_id', $succursaleId))
                    ->where('numero_telephone', $clientPhone)->first();
                if (!$client) throw ValidationException::withMessages(['client_phone' => 'Client introuvable pour ce numéro.']);
            }

            $numero = Facture::genererNumero($entrepriseId);

            $facturePayload = [
                'entreprise_id' => $entrepriseId,
                'numero'        => $numero,
                'tva'           => $tva,
                'total_ht'      => 0,
                'total_tva'     => 0,
                'total_ttc'     => 0,
                'total_montant' => 0,
                'prix_hors_tva' => 0,
                'montant_paye'  => 0,
                'statut'        => $paymentType === 'credit' ? 'en_attente' : 'payee',
                'date_facture'  => now(),
            ];

            if (schema_has_column('factures', 'succursale_id'))              $facturePayload['succursale_id']    = $succursaleId;
            if ($client && schema_has_column('factures', 'client_id'))       $facturePayload['client_id']        = $client->id;
            if ($client && schema_has_column('factures', 'client_nom'))      $facturePayload['client_nom']       = $client->nom_client;
            if ($client && schema_has_column('factures', 'client_telephone')) $facturePayload['client_telephone'] = $client->numero_telephone;
            if (!schema_has_column('factures', 'user_id'))  unset($facturePayload['user_id']);
            else                                             $facturePayload['user_id'] = $userId;
            if (!schema_has_column('factures', 'total_montant')) unset($facturePayload['total_montant']);
            if (!schema_has_column('factures', 'prix_hors_tva')) unset($facturePayload['prix_hors_tva']);
            if (!schema_has_column('factures', 'montant_paye'))  unset($facturePayload['montant_paye']);
            if (!schema_has_column('factures', 'date_facture'))  unset($facturePayload['date_facture']);

            $facture = Facture::create($facturePayload);

            foreach ($validated['lignes'] as $ligne) {
                $produit       = Produit::where('entreprise_id', $entrepriseId)->findOrFail($ligne['produit_id']);
                $prixTtc       = $ligne['prix_unitaire'] ?? $produit->prix_vente;
                $quantite      = (int) $ligne['quantite'];
                $ligneTotalTtc = $quantite * $prixTtc;
                $ligneTotalHt  = $tva > 0 ? $ligneTotalTtc / (1 + ($tva / 100)) : $ligneTotalTtc;
                $ligneTva      = $ligneTotalTtc - $ligneTotalHt;

                $lignePayload = [
                    'facture_id'  => $facture->id,
                    'produit_id'  => $produit->id,
                    'designation' => $produit->nom ?? $produit->designation ?? 'Produit',
                    'quantite'    => $quantite,
                    'prix_ttc'    => $prixTtc,
                    'total'       => $ligneTotalTtc,
                ];
                if (schema_has_column('facture_lignes', 'succursale_id')) $lignePayload['succursale_id'] = $succursaleId;
                FactureLigne::create($lignePayload);

                $this->appliquerMouvementStock($entrepriseId, $produit, 'sortie', $quantite, $prixTtc, 'Vente - ' . ($produit->nom ?? 'Produit'), $userId, $paymentType);

                $totalTtc += $ligneTotalTtc;
                $totalHt  += $ligneTotalHt;
                $totalTva += $ligneTva;
            }

            $totauxPayload = ['total_ht' => $totalHt, 'total_tva' => $totalTva, 'total_ttc' => $totalTtc, 'total_montant' => $totalTtc, 'prix_hors_tva' => $totalHt];
            if (!schema_has_column('factures', 'total_montant')) unset($totauxPayload['total_montant']);
            if (!schema_has_column('factures', 'prix_hors_tva')) unset($totauxPayload['prix_hors_tva']);
            $facture->update($totauxPayload);

            if ($client) {
                if ($paymentType === 'credit') {
                    $client->creance = (float) $client->creance + $totalTtc;
                } elseif ($paymentType === 'reduction') {
                    $reductionDisponible = (float) $client->reduction_accordee;
                    $reductionUtilisee   = min($totalTtc, $reductionDisponible);
                    $resteAPayer         = $totalTtc - $reductionUtilisee;
                    $client->reduction_accordee = max($reductionDisponible - $reductionUtilisee, 0);
                    $client->achat_mensuel = 0;
                    $this->recordReductionUsage($entrepriseId, 'client', $client->id, $reductionUtilisee, (float) $client->reduction_accordee);
                    if ($resteAPayer > 0) {
                        $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Vente (réduction) : {$client->nom_client}", 'date_operation' => now(), 'entree' => $resteAPayer, 'sortie' => 0];
                        if (schema_has_column('caisses', 'succursale_id')) $caisseData['succursale_id'] = $succursaleId;
                        if (schema_has_column('caisses', 'type_operation')) $caisseData['type_operation'] = 'auto';
                        CaisseService::createOperation($caisseData);
                    }
                } else {
                    $this->updateClientStats($client, $totalTtc, $tauxReduction);
                }
                $client->save();
            }

            return $facture;
        });

        $this->archiverFacture($facture);
        return redirect()->route('factures.show', $facture->id);
    }

    // ----------------------------------------------------------------
    // Générer un bon d'entrée (achat)
    // ----------------------------------------------------------------

    public function genererBonEntree(Request $request)
    {
        $validated = $request->validate([
            'fournisseur_id'         => 'required|exists:fournisseurs,id',
            'lignes'                 => 'required|array|min:1',
            'lignes.*.produit_id'    => 'required|exists:produits,id',
            'lignes.*.quantite'      => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'nullable|numeric|min:0',
            'payment_type'           => 'nullable|in:cash,credit,reduction',
            'use_reduction'          => 'nullable|boolean',
        ]);

        $entrepriseId    = auth()->user()->entreprise_id;
        $succursaleId    = session('succursale_id');
        $hasFournisseurs = schema_has_column('fournisseurs', 'succursale_id');
        $useReduction    = (bool) ($validated['use_reduction'] ?? false);
        $paymentType     = $useReduction ? 'reduction' : ($validated['payment_type'] ?? 'cash');

        if ($useReduction && ($validated['payment_type'] ?? null) === 'credit') {
            throw ValidationException::withMessages(['payment_type' => 'La réduction ne peut pas être combinée au crédit.']);
        }

        $bon = DB::transaction(function () use ($validated, $entrepriseId, $paymentType, $useReduction, $succursaleId, $hasFournisseurs) {
            $fournisseur = Fournisseur::where('entreprise_id', $entrepriseId)
                ->when($succursaleId && $hasFournisseurs, fn($q) => $q->where('succursale_id', $succursaleId))
                ->findOrFail($validated['fournisseur_id']);

            $bonPayload = ['entreprise_id' => $entrepriseId, 'fournisseur_id' => $fournisseur->id, 'total_montant' => 0, 'date_bon' => now(), 'payment_type' => $paymentType];
            if (schema_has_column('bon_entrees', 'succursale_id')) $bonPayload['succursale_id'] = $succursaleId;
            $bon   = BonEntree::create($bonPayload);
            $total = 0;

            foreach ($validated['lignes'] as $ligne) {
                $produit      = Produit::where('entreprise_id', $entrepriseId)->findOrFail($ligne['produit_id']);
                $quantite     = (int) $ligne['quantite'];
                $prixUnitaire = (float) ($ligne['prix_unitaire'] ?? $produit->prix_achat);
                $ligneTotal   = $quantite * $prixUnitaire;

                $lignePayload = ['bon_entree_id' => $bon->id, 'produit_id' => $produit->id, 'quantite' => $quantite, 'prix_unitaire' => $prixUnitaire, 'total' => $ligneTotal];
                if (schema_has_column('bon_entree_lignes', 'succursale_id')) $lignePayload['succursale_id'] = $succursaleId;
                BonEntreeLigne::create($lignePayload);

                $this->appliquerMouvementStock($entrepriseId, $produit, 'entree', $quantite, $prixUnitaire, "Bon d'entrée " . $bon->numero, null, $paymentType);
                $total += $ligneTotal;
            }

            $bon->update(['total_montant' => $total]);

            if ($paymentType === 'credit') {
                $fournisseur->dette = (float) $fournisseur->dette + $total;
            } elseif ($paymentType === 'reduction') {
                $reductionDisponible = (float) $fournisseur->reduction_obtenue;
                $reductionUtilisee   = min($total, $reductionDisponible);
                $resteAPayer         = $total - $reductionUtilisee;
                $fournisseur->reduction_obtenue = max($reductionDisponible - $reductionUtilisee, 0);
                $fournisseur->achat_mensuel = 0;
                $this->recordReductionUsage($entrepriseId, 'fournisseur', $fournisseur->id, $reductionUtilisee, (float) $fournisseur->reduction_obtenue);
                if ($resteAPayer > 0) {
                    $caisseData = ['entreprise_id' => $entrepriseId, 'description' => "Achat (réduction) : {$fournisseur->nom_entreprise_fournisseur}", 'date_operation' => now(), 'entree' => 0, 'sortie' => $resteAPayer];
                    if (schema_has_column('caisses', 'succursale_id')) $caisseData['succursale_id'] = $succursaleId;
                    if (schema_has_column('caisses', 'type_operation')) $caisseData['type_operation'] = 'auto';
                    CaisseService::createOperation($caisseData);
                }
            } else {
                $this->updateFournisseurStats($fournisseur, $total);
            }
            $fournisseur->save();
            return $bon;
        });

        $this->archiverBonEntree($bon);
        return redirect()->route('mouvement-stocks.index')->with('success', "Bon d'entrée " . $bon->numero . ' enregistré.');
    }

    // ----------------------------------------------------------------
    // Méthodes privées
    // ----------------------------------------------------------------

    private function appliquerMouvementStock(int $entrepriseId, Produit $produit, string $type, int $quantite, ?float $prixUnitaire, ?string $commentaire = null, ?int $userId = null, ?string $paymentType = 'cash'): void
    {
        $this->mouvementStockService->record($entrepriseId, $produit, $type, $quantite, $prixUnitaire, $commentaire, $userId, $paymentType);
    }

    private function updateClientStats(Client $client, float $montantVente, float $tauxReduction): void
    {
        $client->achat_mensuel      = (float) $client->achat_mensuel + $montantVente;
        $client->reduction_accordee = $tauxReduction > 0 ? round($client->achat_mensuel * ($tauxReduction / 100), 2) : 0;
    }

    private function updateFournisseurStats(Fournisseur $fournisseur, float $montantAchat): void
    {
        $fournisseur->achat_mensuel     = (float) $fournisseur->achat_mensuel + $montantAchat;
        $taux                           = (float) $fournisseur->reduction_pourcentage;
        $fournisseur->reduction_obtenue = $taux > 0 ? round($fournisseur->achat_mensuel * ($taux / 100), 2) : 0;
    }

    private function recordReductionUsage(int $entrepriseId, string $entityType, int $entityId, float $utilise, float $reste): void
    {
        $succursaleId = session('succursale_id');
        $payload = ['entreprise_id' => $entrepriseId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'montant_utilise' => $utilise, 'reste_apres' => $reste];
        if (schema_has_column('reduction_usages', 'succursale_id')) $payload['succursale_id'] = $succursaleId;
        ReductionUsage::create($payload);
    }

    private function archiverFacture(Facture $facture): void
    {
        $exists = Archive::where('entreprise_id', $facture->entreprise_id)
            ->when(schema_has_column('archives', 'succursale_id') && $facture->succursale_id, fn($q) => $q->where('succursale_id', $facture->succursale_id))
            ->where('type', 'facture')
            ->whereDate('date_archive', $facture->date_facture ?? now())
            ->where('reference_id', (string) $facture->id)
            ->exists();
        if ($exists) return;

        $facture->loadMissing('lignes', 'client');
        $archiveData = [
            'entreprise_id' => $facture->entreprise_id,
            'type'          => 'facture',
            'date_archive'  => ($facture->date_facture ?? now())->toDateString(),
            'reference_id'  => (string) $facture->id,
            'payload'       => [
                'id' => $facture->id, 'numero' => $facture->numero, 'date_facture' => $facture->date_facture,
                'client' => $facture->client_nom ?? $facture->client?->nom_client ?? null,
                'client_phone' => $facture->client_telephone ?? $facture->client?->numero_telephone ?? null,
                'total_ht' => $facture->total_ht, 'total_tva' => $facture->total_tva,
                'total_ttc' => $facture->total_ttc ?? $facture->total_montant,
                'tva' => $facture->tva, 'statut' => $facture->statut,
                'lignes' => $facture->lignes->map(fn($l) => ['designation' => $l->designation, 'quantite' => $l->quantite, 'prix_ttc' => $l->prix_ttc, 'total' => $l->total])->toArray(),
            ],
        ];
        if (schema_has_column('archives', 'succursale_id')) $archiveData['succursale_id'] = $facture->succursale_id;
        Archive::create($archiveData);
    }

    private function archiverBonEntree(BonEntree $bon): void
    {
        $exists = Archive::where('entreprise_id', $bon->entreprise_id)
            ->when(schema_has_column('archives', 'succursale_id') && $bon->succursale_id, fn($q) => $q->where('succursale_id', $bon->succursale_id))
            ->where('type', 'bon_entree')
            ->whereDate('date_archive', $bon->date_bon ?? now())
            ->where('reference_id', (string) $bon->id)
            ->exists();
        if ($exists) return;

        $bon->loadMissing('lignes', 'lignes.produit', 'fournisseur');
        $archiveData = [
            'entreprise_id' => $bon->entreprise_id,
            'type'          => 'bon_entree',
            'date_archive'  => ($bon->date_bon ?? now())->toDateString(),
            'reference_id'  => (string) $bon->id,
            'payload'       => [
                'id' => $bon->id, 'numero' => $bon->numero, 'date_bon' => $bon->date_bon,
                'fournisseur' => $bon->fournisseur?->nom_entreprise_fournisseur,
                'total' => $bon->total_montant,
                'lignes' => $bon->lignes->map(fn($l) => ['designation' => $l->produit?->nom ?? 'Produit', 'quantite' => $l->quantite, 'prix_unitaire' => $l->prix_unitaire, 'total' => $l->total])->toArray(),
            ],
        ];
        if (schema_has_column('archives', 'succursale_id')) $archiveData['succursale_id'] = $bon->succursale_id;
        Archive::create($archiveData);
    }
}