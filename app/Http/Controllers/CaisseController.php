<?php

namespace App\Http\Controllers;

use App\Models\Caisse;
use App\Models\Parametre;
use App\Models\Succursale;
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
            ->when(
                Schema::hasColumn('caisses', 'succursale_id') && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->orderByRaw('COALESCE(date_operation, created_at) ASC')
            ->get();

        // ✅ Au dashboard central, préfixer chaque description par le nom
        // de la succursale pour identifier l'origine de chaque opération.
        if (!$succursaleId && Schema::hasColumn('caisses', 'succursale_id')) {
            $succursaleIds = $caissesAsc->pluck('succursale_id')->filter()->unique()->values();
            $succursales   = Succursale::whereIn('id', $succursaleIds)
                ->where('entreprise_id', $entrepriseId)
                ->pluck('nom', 'id'); // [id => nom]

            $caissesAsc = $caissesAsc->map(function ($c) use ($succursales) {
                if ($c->succursale_id && isset($succursales[$c->succursale_id])) {
                    $prefix = '[' . $succursales[$c->succursale_id] . '] ';
                    // Éviter de doubler le préfixe si déjà présent
                    if (!str_starts_with((string) $c->description, $prefix)) {
                        $c->description = $prefix . ($c->description ?? '');
                    }
                }
                return $c;
            });
        }

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
                ->when(
                    Schema::hasColumn('caisses', 'succursale_id') && $succursaleId,
                    fn($q) => $q->where('succursale_id', $succursaleId)
                )
                ->where('type_operation', 'initial')
                ->orderBy('created_at')
                ->first();
        }

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise     = $parametres?->devise ?? 'CDF';

        return Inertia::render('Caisse/Index', [
            'caisses'        => $caisses,
            'devise'         => $devise,
            'hasInitial'     => (bool) $caisseInitiale,
            'caisseInitiale' => $caisseInitiale,
            'succursale_id'  => $succursaleId,
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
            ->when(
                Schema::hasColumn('caisses', 'succursale_id') && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->where('type_operation', 'initial')
            ->exists();

        if ($initialExiste) {
            return response()->json([
                'message' => 'Le solde initial est déjà défini pour cette entreprise.',
            ], 422);
        }

        $validated = $request->validate([
            'montant'        => 'required|numeric|min:0',
            'description'    => 'nullable|string|max:255',
            'date_operation' => 'nullable|date',
        ]);

        $payload = [
            'entreprise_id'  => $entrepriseId,
            'date_operation' => $validated['date_operation'] ?? now(),
            'description'    => $validated['description'] ?? 'Solde initial (manuel)',
            'entree'         => $validated['montant'],
            'sortie'         => 0,
            'type_operation' => 'initial',
        ];
        if (Schema::hasColumn('caisses', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        CaisseService::createOperation($payload);

        return response()->json(['message' => 'Solde initial enregistré avec succès.']);
    }
}