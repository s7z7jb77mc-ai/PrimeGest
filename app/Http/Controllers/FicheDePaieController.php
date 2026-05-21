<?php

namespace App\Http\Controllers;

use App\Models\FicheDePaie;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FicheDePaieController extends Controller
{
    // Afficher la liste des fiches de paie
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // Récupérer les fiches de paie
        $fiches = FicheDePaie::with('employe')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('employes', 'succursale_id'), function ($q) use ($succursaleId) {
                $q->whereHas('employe', fn($qe) => $qe->where('succursale_id', $succursaleId));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Récupérer tous les employés de l'entreprise pour le select
        $employes = Employe::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && schema_has_column('employes', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->get();

        return Inertia::render('FichesDePaie/Index', [
            'fiches' => $fiches,
            'employes' => $employes, // ← nécessaire pour le select
        ]);
    }

    // Créer une nouvelle fiche de paie
    public function store(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'employe_id'    => ['required', Rule::exists('employes', 'id')->where(fn($query) => $query->where('entreprise_id', $entrepriseId))],
            'mois'          => 'required|integer|min:1|max:12', // Mois en entier
            'annee'         => 'required|integer',
            'primes'        => 'nullable|numeric|min:0',
            'retenues'      => 'nullable|numeric|min:0',
            'statut'        => 'required|in:en_attente,payé',
            'date_paiement' => 'nullable|date',
        ]);

        // Récupérer le salaire de base depuis l'employé
        $employeQuery = Employe::where('entreprise_id', $entrepriseId);
        if ($succursaleId && schema_has_column('employes', 'succursale_id')) {
            $employeQuery->where('succursale_id', $succursaleId);
        }
        $employe = $employeQuery->findOrFail($validated['employe_id']);
        $validated['salaire_base'] = $employe->salaire_base;

        $validated['entreprise_id'] = $entrepriseId;

        // Calculer net à payer automatiquement
        $validated['net_a_payer'] = $validated['salaire_base']
                                    + ($validated['primes'] ?? 0)
                                    - ($validated['retenues'] ?? 0);

        FicheDePaie::create($validated);

        return redirect()->route('fiches.index')
                         ->with('success', 'Fiche de paie créée avec succès.');
    }

    // Modifier une fiche de paie
    public function update(Request $request, FicheDePaie $fiche)
    {
        $this->assertFicheBelongsToCurrentEntreprise($fiche);

        $validated = $request->validate([
            'primes'        => 'nullable|numeric|min:0',
            'retenues'      => 'nullable|numeric|min:0',
            'statut'        => 'required|in:en_attente,payé',
            'date_paiement' => 'nullable|date',
        ]);

        // Recalculer net à payer
        $validated['net_a_payer'] = $fiche->salaire_base
                                    + ($validated['primes'] ?? 0)
                                    - ($validated['retenues'] ?? 0);

        $fiche->update($validated);

        return redirect()->route('fiches.index')
                         ->with('success', 'Fiche de paie mise à jour avec succès.');
    }

    // Supprimer une fiche de paie
    public function destroy(FicheDePaie $fiche)
    {
        $this->assertFicheBelongsToCurrentEntreprise($fiche);

        $fiche->delete();

        return redirect()->route('fiches.index')
                         ->with('success', 'Fiche de paie supprimée avec succès.');
    }
    // FicheDePaieController.php

    public function confirmerPaiement(FicheDePaie $fiche)
    {
        $this->assertFicheBelongsToCurrentEntreprise($fiche);

        $fiche->update([
            'statut_paiement' => 'payee', // Déclenche l'observer
            'date_paiement' => now(),
        ]);

        return redirect()->route('fiches.index')
                        ->with('success', 'Paiement confirmé avec succès et enregistré dans la caisse.');
    }

    private function assertFicheBelongsToCurrentEntreprise(FicheDePaie $fiche): void
    {
        if ($fiche->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403, 'Action non autorisée.');
        }
    }

}
