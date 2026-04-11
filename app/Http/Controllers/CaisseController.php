<?php
namespace App\Http\Controllers;

use App\Models\Caisse;
use App\Models\Parametre;
use App\Services\CaisseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CaisseController extends Controller
{
    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $caissesAsc = Caisse::where('entreprise_id', $entrepriseId)
            ->when(Schema::hasColumn('caisses', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->orderByRaw('COALESCE(date_operation, created_at) ASC')
            ->get();

        // Calculer le solde cumulé dans l'ordre chronologique
        $solde = 0;
        foreach ($caissesAsc as $caisse) {
            $solde += (float) $caisse->entree - (float) $caisse->sortie;
            $caisse->solde_cumule = $solde;
        }

        // Afficher du plus récent au plus ancien
        $caisses = $caissesAsc->reverse()->values();

        $caisseInitiale = null;
        if (Schema::hasColumn('caisses', 'type_operation')) {
            $caisseInitiale = Caisse::where('entreprise_id', $entrepriseId)
                ->when(Schema::hasColumn('caisses', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
                ->where('type_operation', 'initial')
                ->orderBy('created_at')
                ->first();
        }

        // Récupérer la devise depuis les paramètres
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';

        return Inertia::render('Caisse/Index', [
            'caisses' => $caisses,
            'devise' => $devise,
            'hasInitial' => (bool) $caisseInitiale,
            'caisseInitiale' => $caisseInitiale,
            'succursale_id' => $succursaleId,
        ]);
    }

    public function storeInitial(Request $request)
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');

        if ($succursaleId) {
            return response()->json([
                'message' => 'Le solde initial se définit uniquement au niveau central.',
            ], 422);
        }

        if (!Schema::hasColumn('caisses', 'type_operation')) {
            return response()->json([
                'message' => 'Veuillez exécuter la migration pour activer le solde initial.',
            ], 500);
        }

        $initialExiste = Caisse::where('entreprise_id', $entrepriseId)
            ->when(Schema::hasColumn('caisses', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('type_operation', 'initial')
            ->exists();

        if ($initialExiste) {
            return response()->json([
                'message' => 'Le solde initial est déjà défini pour cette entreprise.',
            ], 422);
        }

        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'date_operation' => 'nullable|date',
        ]);

        $montant = $validated['montant'];
        $description = $validated['description'] ?? 'Solde initial (manuel)';
        $dateOperation = $validated['date_operation'] ?? now();

        $payload = [
            'entreprise_id' => $entrepriseId,
            'date_operation' => $dateOperation,
            'description' => $description,
            'entree' => $montant,
            'sortie' => 0,
            'type_operation' => 'initial',
        ];
        if (Schema::hasColumn('caisses', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        CaisseService::createOperation($payload);

        return response()->json([
            'message' => 'Solde initial enregistré avec succès.',
        ]);
    }
}
