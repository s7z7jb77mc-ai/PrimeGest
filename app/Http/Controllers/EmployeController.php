<?php

namespace App\Http\Controllers;

use App\Models\Employe;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class EmployeController extends Controller
{
    /** Afficher la liste des employés */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $employes = Employe::query()
            ->with('succursale')
            ->where('entreprise_id', $entrepriseId) // On filtre par entreprise
            ->when($succursaleId && \Illuminate\Support\Facades\Schema::hasColumn('employes', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('poste', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $succursales = Succursale::where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->get(['id', 'nom']);
        $hasSuccursales = $succursales->isNotEmpty();

        return Inertia::render('Employes/Index', [
            'employes' => $employes,
            'filters'  => ['search' => $search],
            'succursales' => $succursales,
            'has_succursales' => $hasSuccursales,
        ]);
    }

    /** Ajouter un employé */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:255',
            'prenom'       => 'nullable|string|max:255',
            'email'        => 'required|email|unique:employes,email',
            'telephone'    => 'nullable|string|max:20',
            'poste'        => 'nullable|string|max:255',
            'salaire_base' => 'required|numeric|min:0',
            'date_embauche'=> 'nullable|date',
            'statut'       => 'required|in:actif,inactif',
            'succursale_id' => [
                'nullable',
                Rule::exists('succursales', 'id')->where(fn($q) => $q->where('entreprise_id', auth()->user()->entreprise_id)),
            ],
        ]);

        // Ajout automatique de l’entreprise de l’utilisateur connecté
        $validated['entreprise_id'] = auth()->user()->entreprise_id;

        $sessionSuccursale = session('succursale_id');
        if ($sessionSuccursale) {
            $validated['succursale_id'] = $sessionSuccursale;
        }

        Employe::create($validated);

        return redirect()->route('employes.index')->with('success', 'Employé ajouté avec succès.');
    }

    /** Modifier un employé */
    public function update(Request $request, Employe $employe)
    {
        // Vérifier que l’employé appartient bien à l’entreprise de l’utilisateur
        if ($employe->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403, 'Action non autorisée.');
        }
        $sessionSuccursale = session('succursale_id');
        if ($sessionSuccursale && $employe->succursale_id && (int) $employe->succursale_id !== (int) $sessionSuccursale) {
            abort(403, 'Action non autorisée.');
        }
        $this->authorize('update', $employe);
        $this->assertSuperAdmin($request);

        $validated = $request->validate([
            'nom'          => 'required|string|max:255',
            'prenom'       => 'nullable|string|max:255',
            'email'        => 'required|email|unique:employes,email,' . $employe->id,
            'telephone'    => 'nullable|string|max:20',
            'poste'        => 'nullable|string|max:255',
            'salaire_base' => 'required|numeric|min:0',
            'date_embauche'=> 'nullable|date',
            'statut'       => 'required|in:actif,inactif',
            'succursale_id' => [
                'nullable',
                Rule::exists('succursales', 'id')->where(fn($q) => $q->where('entreprise_id', auth()->user()->entreprise_id)),
            ],
        ]);

        // On force toujours l’entreprise
        $validated['entreprise_id'] = auth()->user()->entreprise_id;
        if ($sessionSuccursale) {
            $validated['succursale_id'] = $sessionSuccursale;
        }

        $employe->update($validated);

        return redirect()->route('employes.index')->with('success', 'Employé mis à jour avec succès.');
    }

    /** Supprimer un employé */
    public function destroy(Employe $employe)
    {
        // Vérifier que l’employé appartient bien à l’entreprise de l’utilisateur
        if ($employe->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403, 'Action non autorisée.');
        }
        $this->authorize('delete', $employe);
        $this->assertSuperAdmin(request());

        $employe->delete();

        return redirect()->route('employes.index')->with('success', 'Employé supprimé avec succès.');
    }

    private function assertSuperAdmin(Request $request): void
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
