<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FournisseurController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $search       = trim((string) $request->get('search', ''));

        // ✅ withoutGlobalScopes() contourne HasSuccursaleScope
        // Fournisseurs visibles par toute l'entreprise — pas de filtre succursale
        $fournisseurs = Fournisseur::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when($search !== '', fn($q) => $q->where('nom_entreprise_fournisseur', 'like', "%{$search}%"))
            ->orderBy('nom_entreprise_fournisseur')
            ->get();

        return Inertia::render('Fournisseurs/Index', [
            'fournisseurs' => $fournisseurs,
            'filters'      => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'nom_entreprise_fournisseur' => 'required|string|max:255',
            'adresse'                    => 'nullable|string|max:255',
            'reduction_pourcentage'      => 'nullable|numeric|min:0',
        ]);

        $validated['entreprise_id'] = $entrepriseId;

        // Succursale_id gardée pour traçabilité uniquement
        if ($succursaleId && schema_has_column('fournisseurs', 'succursale_id')) {
            $validated['succursale_id'] = $succursaleId;
        }

        Fournisseur::create($validated);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur ajouté avec succès.');
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $this->authorize('update', $fournisseur);
        $this->assertManagerOrSuperAdmin($request);

        $validated = $request->validate([
            'nom_entreprise_fournisseur' => 'required|string|max:255',
            'adresse'                    => 'nullable|string|max:255',
            'reduction_pourcentage'      => 'nullable|numeric|min:0',
        ]);

        $fournisseur->update($validated);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $this->authorize('delete', $fournisseur);
        $this->assertManagerOrSuperAdmin(request());

        $fournisseur->delete();

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur supprimé avec succès.');
    }

    protected function authorizeFournisseur(Fournisseur $fournisseur): void
    {
        if ($fournisseur->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403);
        }
    }

    protected function assertManagerOrSuperAdmin(Request $request): void
    {
        $user         = $request->user();
        $succursaleId = session('succursale_id');
        $isSuperAdmin = $user->isSuperAdmin();
        $isManager    = false;

        if (!$isSuperAdmin && $succursaleId) {
            $succursale = Succursale::where('id', $succursaleId)
                ->where('entreprise_id', $user->entreprise_id)
                ->first();
            $isManager = $succursale && (int) $succursale->manager_user_id === (int) $user->id;
        }

        if (!$isSuperAdmin && !$isManager) {
            abort(403, 'Accès réservé au Super Admin ou au manager de la succursale.');
        }

        $password = (string) $request->input('admin_password', '');
        if ($password === '' || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe incorrect.',
            ]);
        }
    }
}