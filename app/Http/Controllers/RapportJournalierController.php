<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RapportJournalierController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Afficher le rapport journalier de la journée en cours
     */
    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $today = now()->toDateString();

        // Récupérer tous les mouvements de stock du jour
        $mouvements = MouvementStock::with('produit', 'user')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('created_at', $today)
            ->get();

        // Récupérer tous les journaux du jour (dépenses)
        $journaux = Journal::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('dateHeure_operation', $today)
            ->get();

        // Créer le tableau de rapport détaillé par produit
        $rapportProduits = $this->genererRapportProduits($mouvements, $entrepriseId, $succursaleId);

        // Récupérer les stocks actuels (quantités finales)
        $stocks = Stock::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get()
            ->keyBy('produit_id');

        // Ajouter les données de stock final aux produits du rapport
        foreach ($rapportProduits as &$rapport) {
            $produitId = $rapport['produit_id'];
            if (isset($stocks[$produitId])) {
                $rapport['stock_final_quantite'] = $stocks[$produitId]->quantite;
                $rapport['stock_final_valeur'] = $stocks[$produitId]->quantite * $stocks[$produitId]->prix_vente;
            }
        }

        // Calculer les résumés
        $resume = $this->calculerResume($rapportProduits, $journaux);

        // Formater les journaux pour le tableau des dépenses
        $depenses = $journaux->map(function ($journal) {
            return [
                'id' => $journal->id,
                'libelle' => $journal->description,
                'montant' => $journal->montant,
                'user' => 'N/A',  // Journal n'a pas de relation user
                'heure' => $journal->dateHeure_operation->format('H:i:s'),
                'date' => $journal->dateHeure_operation->format('Y-m-d'),
            ];
        })->toArray();

        $totalDepenses = $journaux->sum('montant');

        return Inertia::render('RapportJournalier/Index', [
            'rapportProduits' => $rapportProduits,
            'resume' => $resume,
            'depenses' => $depenses,
            'totalDepenses' => $totalDepenses,
            'date' => $today,
        ]);
    }

    /**
     * Générer le rapport détaillé par produit
     */
    private function genererRapportProduits($mouvements, int $entrepriseId, ?int $succursaleId = null)
    {
        $rapport = [];

        // Récupérer tous les stocks initiaux pour la journée
        $stocks = Stock::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->get()
            ->keyBy('produit_id');

        foreach ($mouvements as $mouvement) {
            $produitId = $mouvement->produit_id;

            if (!isset($rapport[$produitId])) {
                // Initialiser le produit
                $stockInitial = $stocks[$produitId] ?? null;
                
                $rapport[$produitId] = [
                    'produit_id' => $produitId,
                    'produit_nom' => $mouvement->produit->nom ?? 'N/A',
                    'prix_unitaire' => $mouvement->prix_unitaire ?? 0,
                    'stock_initial_quantite' => $stockInitial ? $stockInitial->quantite : 0,
                    'stock_initial_valeur' => $stockInitial ? ($stockInitial->quantite * $stockInitial->prix_achat) : 0,
                    'entree_quantite' => 0,
                    'entree_valeur' => 0,
                    'sortie_quantite' => 0,
                    'sortie_valeur' => 0,
                    'stock_final_quantite' => 0,
                    'stock_final_valeur' => 0,
                    'operations' => [],
                ];
            }

            // Ajouter le mouvement
            if ($mouvement->type === 'entree') {
                $rapport[$produitId]['entree_quantite'] += $mouvement->quantite;
                $rapport[$produitId]['entree_valeur'] += $mouvement->quantite * $mouvement->prix_unitaire;
            } else {
                $rapport[$produitId]['sortie_quantite'] += $mouvement->quantite;
                $rapport[$produitId]['sortie_valeur'] += $mouvement->quantite * $mouvement->prix_unitaire;
            }

            // Ajouter l'opération pour le détail
            $rapport[$produitId]['operations'][] = [
                'type' => $mouvement->type,
                'quantite' => $mouvement->quantite,
                'prix_unitaire' => $mouvement->prix_unitaire,
                'montant' => $mouvement->quantite * $mouvement->prix_unitaire,
                'user' => $mouvement->user ? $mouvement->user->name : 'N/A',
                'heure' => $mouvement->created_at->format('H:i:s'),
            ];
        }

        // Calculer le stock final = initial + entrees - sorties
        foreach ($rapport as &$r) {
            $r['stock_final_quantite'] = $r['stock_initial_quantite'] + $r['entree_quantite'] - $r['sortie_quantite'];
            $r['stock_final_valeur'] = $r['stock_final_quantite'] * $r['prix_unitaire'];
        }

        return array_values($rapport);
    }

    /**
     * Calculer le résumé financier
     */
    private function calculerResume($rapportProduits, $journaux)
    {
        $resume = [
            'stock_initial_total_valeur' => 0,
            'entree_total_valeur' => 0,
            'sortie_total_valeur' => 0,
            'stock_final_total_valeur' => 0,
            'recette_journaliere' => 0,
            'total_depenses' => 0,
        ];

        foreach ($rapportProduits as $rapport) {
            $resume['stock_initial_total_valeur'] += $rapport['stock_initial_valeur'];
            $resume['entree_total_valeur'] += $rapport['entree_valeur'];
            $resume['sortie_total_valeur'] += $rapport['sortie_valeur'];
            $resume['stock_final_total_valeur'] += $rapport['stock_final_valeur'];
        }

        // Recette journalière = Stock initial + Entrées - Stock final
        $resume['recette_journaliere'] = $resume['stock_initial_total_valeur'] + $resume['entree_total_valeur'] - $resume['stock_final_total_valeur'];

        // Total dépenses
        $resume['total_depenses'] = $journaux->sum('montant');

        return $resume;
    }
}
