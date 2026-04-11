<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FournisseurController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $search = trim((string) $request->get('search', ''));

        $query = Fournisseur::query()->where('entreprise_id', $entrepriseId);
        if ($succursaleId && Schema::hasColumn('fournisseurs', 'succursale_id')) {
            $query->where('succursale_id', $succursaleId);
        }
        if ($search !== '') {
            $query->where('nom_entreprise_fournisseur', 'like', "%{$search}%");
        }

        $fournisseurs = $query->orderBy('nom_entreprise_fournisseur')->get();

        return Inertia::render('Fournisseurs/Index', [
            'fournisseurs' => $fournisseurs,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'nom_entreprise_fournisseur' => 'required|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'reduction_pourcentage' => 'nullable|numeric|min:0',
        ]);

        $validated['entreprise_id'] = $entrepriseId;
        if ($succursaleId && Schema::hasColumn('fournisseurs', 'succursale_id')) {
            $validated['succursale_id'] = $succursaleId;
        }
        Fournisseur::create($validated);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur ajouté avec succès.');
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $this->authorize('update', $fournisseur);
        $this->assertSuperAdmin($request);
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'nom_entreprise_fournisseur' => 'required|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'reduction_pourcentage' => 'nullable|numeric|min:0',
        ]);

        if ($succursaleId && $fournisseur->succursale_id && (int) $fournisseur->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
        $fournisseur->update($validated);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $this->authorize('delete', $fournisseur);
        $this->assertSuperAdmin(request());
        $succursaleId = session('succursale_id');
        if ($succursaleId && $fournisseur->succursale_id && (int) $fournisseur->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
        $fournisseur->delete();

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur supprimé avec succès.');
    }

    protected function authorizeFournisseur(Fournisseur $fournisseur): void
    {
        if ($fournisseur->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403);
        }
    }

    protected function assertSuperAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Accès réservé au Super Admin.');
        }

        $password = (string) $request->input('admin_password', '');
        if ($password === '' || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe Super Admin incorrect.',
            ]);
        }
    }
}
