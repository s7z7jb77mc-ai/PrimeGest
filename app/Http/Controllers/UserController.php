<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    // Afficher tous les utilisateurs + employés pour le formulaire
    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $users = User::with('employe')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId && \Illuminate\Support\Facades\Schema::hasColumn('employes', 'succursale_id'), function ($q) use ($succursaleId) {
                $q->whereHas('employe', fn($qe) => $qe->where('succursale_id', $succursaleId));
            })
            ->get();
        $employes = Employe::where('entreprise_id', $entrepriseId)
            ->when($succursaleId && \Illuminate\Support\Facades\Schema::hasColumn('employes', 'succursale_id'), fn($q) => $q->where('succursale_id', $succursaleId))
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'employes' => $employes,
        ]);
    }

    // Méthode privée pour vérifier le mot de passe super admin
    private function checkAdminPassword(Request $request)
    {
        $request->validate(['admin_password' => 'required|string']);
        $admin = Auth::user();

        if (!Hash::check($request->admin_password, $admin->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe super admin incorrect.',
            ]);
        }
    }

    private function assertSuperAdmin(): void
    {
        if (Auth::user()?->role !== 'super_admin') {
            abort(403, 'Accès réservé au Super Admin.');
        }
    }

    // Créer un utilisateur
    public function store(Request $request)
    {
        $this->assertSuperAdmin();
        $this->checkAdminPassword($request);

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string',
            'password' => 'required|string|confirmed|min:6',
            'employe_id' => 'nullable|exists:employes,id',
            'access_pages' => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        // Si un employé est choisi, on remplace l'email par l'email de l'employé
        if ($request->employe_id) {
            $employe = Employe::find($request->employe_id);
            if ($employe && $employe->email) {
                $request->merge(['email' => $employe->email]);
            }
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'employe_id' => $request->employe_id,
            'password' => Hash::make($request->password),
            'entreprise_id' => Auth::user()->entreprise_id,
            'access_pages' => $request->access_pages ?? [],
        ]);

        return redirect()->back()->with('success', 'Utilisateur créé avec succès.');
    }

    // Mettre à jour un utilisateur
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $this->assertSuperAdmin();
        $this->checkAdminPassword($request);
        if ($user->entreprise_id !== Auth::user()->entreprise_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|string',
            'password' => 'nullable|string|confirmed|min:6',
            'employe_id' => 'nullable|exists:employes,id',
            'access_pages' => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        // Si un employé est choisi, on remplace l'email par l'email de l'employé
        if ($request->employe_id) {
            $employe = Employe::find($request->employe_id);
            if ($employe && $employe->email) {
                $request->merge(['email' => $employe->email]);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->employe_id = $request->employe_id;
        $user->access_pages = $request->access_pages ?? [];

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->back()->with('success', 'Utilisateur mis à jour avec succès.');
    }

    // Supprimer un utilisateur
    public function destroy(User $user, Request $request)
    {
        $this->authorize('delete', $user);
        $this->assertSuperAdmin();
        $this->checkAdminPassword($request);
        if ($user->entreprise_id !== Auth::user()->entreprise_id) {
            abort(403);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    public function updateAccess(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $this->assertSuperAdmin();
        if ($user->entreprise_id !== Auth::user()->entreprise_id) {
            abort(403);
        }

        $data = $request->validate([
            'access_pages' => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        $user->access_pages = $data['access_pages'] ?? [];
        $user->save();

        return redirect()->back()->with('success', 'Accès utilisateur mis à jour.');
    }
    protected $appends = ['is_super_admin'];

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->isSuperAdmin(); // ta méthode existante
    }
}
