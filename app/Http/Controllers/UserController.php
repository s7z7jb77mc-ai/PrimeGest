<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employe;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    // ----------------------------------------------------------------
    // Rôles disponibles selon le contexte
    // ✅ Au central (super admin) : tous les rôles
    // ✅ Dans une succursale (manager) : seulement admin et user
    // ----------------------------------------------------------------

    private function availableRoles(?int $succursaleId): array
    {
        if ($succursaleId) {
            // Dans une succursale : pas de super_admin dans la liste
            return [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'user',  'label' => 'Utilisateur'],
            ];
        }
        // Dashboard central
        return [
            ['value' => 'super_admin', 'label' => 'Super Admin'],
            ['value' => 'admin',       'label' => 'Admin'],
            ['value' => 'user',        'label' => 'Utilisateur'],
        ];
    }

    // ----------------------------------------------------------------
    // Liste des utilisateurs
    // ----------------------------------------------------------------

    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $users = User::with('employe')
            ->where('entreprise_id', $entrepriseId)
            ->when(
                $succursaleId && Schema::hasColumn('employes', 'succursale_id'),
                fn($q) => $q->whereHas('employe', fn($qe) => $qe->where('succursale_id', $succursaleId))
            )
            ->get();

        $employes = Employe::where('entreprise_id', $entrepriseId)
            ->when(
                $succursaleId && Schema::hasColumn('employes', 'succursale_id'),
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->get();

        return Inertia::render('Users/Index', [
            'users'          => $users,
            'employes'       => $employes,
            // ✅ On passe les rôles disponibles au frontend
            'availableRoles' => $this->availableRoles($succursaleId),
        ]);
    }

    // ----------------------------------------------------------------
    // Créer un utilisateur
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $currentUser  = Auth::user();
        $succursaleId = session('succursale_id');

        $this->assertManagerOrSuperAdmin($request, $currentUser, $succursaleId);

        $request->validate([
            'name'           => 'required|string',
            'email'          => 'required|email|unique:users,email',
            'role'           => 'required|string',
            'password'       => 'required|string|confirmed|min:6',
            'employe_id'     => 'nullable|exists:employes,id',
            'access_pages'   => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        // ✅ Manager ne peut pas créer un super_admin
        if (!$currentUser->isSuperAdmin() && $request->role === 'super_admin') {
            abort(403, 'Un manager ne peut pas créer un Super Admin.');
        }

        // ✅ Manager : l'employé doit appartenir à sa succursale
        if ($succursaleId && !$currentUser->isSuperAdmin() && $request->employe_id) {
            $employe = Employe::find($request->employe_id);
            if ($employe && (int) $employe->succursale_id !== (int) $succursaleId) {
                throw ValidationException::withMessages([
                    'employe_id' => 'Cet employé n\'appartient pas à votre succursale.',
                ]);
            }
        }

        if ($request->employe_id) {
            $employe = Employe::find($request->employe_id);
            if ($employe && $employe->email) {
                $request->merge(['email' => $employe->email]);
            }
        }

        User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'role'          => $request->role,
            'employe_id'    => $request->employe_id,
            'password'      => Hash::make($request->password),
            'entreprise_id' => $currentUser->entreprise_id,
            'access_pages'  => $request->access_pages ?? [],
        ]);

        return redirect()->back()->with('success', 'Utilisateur créé avec succès.');
    }

    // ----------------------------------------------------------------
    // Modifier un utilisateur
    // ----------------------------------------------------------------

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $currentUser  = Auth::user();
        $succursaleId = session('succursale_id');

        if ($user->entreprise_id !== $currentUser->entreprise_id) abort(403);

        // ✅ Manager : ne peut modifier que les users de SA succursale
        if (!$currentUser->isSuperAdmin() && $succursaleId) {
            $employe = $user->employe;
            if (!$employe || (int) $employe->succursale_id !== (int) $succursaleId) {
                abort(403, 'Vous ne pouvez modifier que les utilisateurs de votre succursale.');
            }
        }

        $this->assertManagerOrSuperAdmin($request, $currentUser, $succursaleId);

        $request->validate([
            'name'           => 'required|string',
            'email'          => 'required|email|unique:users,email,' . $user->id,
            'role'           => 'required|string',
            'password'       => 'nullable|string|confirmed|min:6',
            'employe_id'     => 'nullable|exists:employes,id',
            'access_pages'   => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        // ✅ Manager ne peut pas attribuer super_admin
        if (!$currentUser->isSuperAdmin() && $request->role === 'super_admin') {
            abort(403, 'Un manager ne peut pas attribuer le rôle Super Admin.');
        }

        if ($request->employe_id) {
            $employe = Employe::find($request->employe_id);
            if ($employe && $employe->email) {
                $request->merge(['email' => $employe->email]);
            }
        }

        $user->name         = $request->name;
        $user->email        = $request->email;
        $user->role         = $request->role;
        $user->employe_id   = $request->employe_id;
        $user->access_pages = $request->access_pages ?? [];

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->back()->with('success', 'Utilisateur mis à jour avec succès.');
    }

    // ----------------------------------------------------------------
    // Supprimer un utilisateur
    // ----------------------------------------------------------------

    public function destroy(User $user, Request $request)
    {
        $this->authorize('delete', $user);
        $currentUser  = Auth::user();
        $succursaleId = session('succursale_id');

        if ($user->entreprise_id !== $currentUser->entreprise_id) abort(403);

        if (!$currentUser->isSuperAdmin() && $succursaleId) {
            $employe = $user->employe;
            if (!$employe || (int) $employe->succursale_id !== (int) $succursaleId) {
                abort(403, 'Vous ne pouvez supprimer que les utilisateurs de votre succursale.');
            }
        }

        $this->assertManagerOrSuperAdmin($request, $currentUser, $succursaleId);

        $user->delete();

        return redirect()->back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    // ----------------------------------------------------------------
    // Mettre à jour les accès
    // ----------------------------------------------------------------

    public function updateAccess(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $currentUser  = Auth::user();
        $succursaleId = session('succursale_id');

        if ($user->entreprise_id !== $currentUser->entreprise_id) abort(403);

        if (!$currentUser->isSuperAdmin()) {
            if (!$succursaleId) abort(403, 'Accès réservé au Super Admin.');
            $this->ensureIsManager($currentUser, $succursaleId);
            $employe = $user->employe;
            if (!$employe || (int) $employe->succursale_id !== (int) $succursaleId) {
                abort(403, 'Vous ne pouvez gérer que les accès des utilisateurs de votre succursale.');
            }
        }

        $data = $request->validate([
            'access_pages'   => 'nullable|array',
            'access_pages.*' => 'string',
        ]);

        $user->access_pages = $data['access_pages'] ?? [];
        $user->save();

        return redirect()->back()->with('success', 'Accès utilisateur mis à jour.');
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    private function assertManagerOrSuperAdmin(Request $request, User $currentUser, ?int $succursaleId): void
    {
        $isSuperAdmin = $currentUser->isSuperAdmin();
        $isManager    = false;

        if (!$isSuperAdmin && $succursaleId) {
            $succursale = Succursale::where('id', $succursaleId)
                ->where('entreprise_id', $currentUser->entreprise_id)
                ->first();
            $isManager = $succursale && (int) $succursale->manager_user_id === (int) $currentUser->id;
        }

        if (!$isSuperAdmin && !$isManager) {
            abort(403, 'Accès réservé au Super Admin ou au manager de la succursale.');
        }

        // ✅ Vérifier le mot de passe du USER CONNECTÉ (manager ou super admin)
        $request->validate(['admin_password' => 'required|string']);
        if (!Hash::check($request->admin_password, $currentUser->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe incorrect.',
            ]);
        }
    }

    private function ensureIsManager(User $user, int $succursaleId): void
    {
        $succursale = Succursale::where('id', $succursaleId)
            ->where('entreprise_id', $user->entreprise_id)
            ->first();

        if (!$succursale || (int) $succursale->manager_user_id !== (int) $user->id) {
            abort(403, 'Accès réservé au manager de cette succursale.');
        }
    }
}