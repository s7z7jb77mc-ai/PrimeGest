<?php

namespace App\Http\Controllers;

use App\Models\Succursale;
use App\Models\User;
use App\Models\Parametre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SuccursaleController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $entrepriseId = $user->entreprise_id;
        $this->ensureMultiSuccursalesEnabled($entrepriseId);

        $managers = User::with('employe')
            ->where('entreprise_id', $entrepriseId)
            ->whereNotNull('employe_id')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employe_nom' => $u->employe?->nom,
                    'role' => $u->role,
                ];
            })
            ->values();

        $succursales = Succursale::with('manager')
            ->where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'nom' => $s->nom,
                    'adresse' => $s->adresse,
                    'manager' => $s->manager?->name ?? $s->manager?->employe?->nom,
                    'active' => (bool) $s->active,
                ];
            })
            ->values();

        return inertia('Succursales/Index', [
            'succursales' => $succursales,
            'managers' => $managers,
            'can_manage' => $user?->isSuperAdmin() === true,
        ]);
    }

    public function show(Succursale $succursale)
    {
        $user = Auth::user();
        $this->ensureMultiSuccursalesEnabled($user->entreprise_id);
        if ($succursale->entreprise_id !== $user->entreprise_id) {
            abort(403);
        }

        // Définir la succursale active dans la session
        session(['succursale_id' => $succursale->id]);

        // Redirige vers le dashboard de la succursale avec la sidebar complète
        return redirect('/dashboard');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Accès réservé au Super Admin.');
        }
        $this->ensureMultiSuccursalesEnabled($user->entreprise_id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'manager_user_id' => 'required|exists:users,id',
            'active' => 'nullable|boolean',
            'admin_password' => 'required|string',
        ]);

        if (!Hash::check($validated['admin_password'], $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe Super Admin incorrect.',
            ]);
        }
        unset($validated['admin_password']);

        $validated['entreprise_id'] = $user->entreprise_id;

        if (!empty($validated['manager_user_id'])) {
            $manager = User::where('id', $validated['manager_user_id'])
                ->where('entreprise_id', $user->entreprise_id)
                ->first();
            if (!$manager) {
                return response()->json(['errors' => ['manager_user_id' => ['Manager invalide.']]], 422);
            }
            $manager->manager = true;
            $manager->save();
        }

        $validated['active'] = (bool) ($validated['active'] ?? true);
        $succursale = Succursale::create($validated);

        return response()->json([
            'success' => true,
            'succursale' => $succursale,
        ]);
    }

    public function update(Request $request, Succursale $succursale)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Accès réservé au Super Admin.');
        }
        $this->ensureMultiSuccursalesEnabled($user->entreprise_id);

        if ($succursale->entreprise_id !== $user->entreprise_id) {
            abort(403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'manager_user_id' => 'required|exists:users,id',
            'active' => 'nullable|boolean',
            'admin_password' => 'required|string',
        ]);

        if (!Hash::check($validated['admin_password'], $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe Super Admin incorrect.',
            ]);
        }
        unset($validated['admin_password']);

        if (!empty($validated['manager_user_id'])) {
            $manager = User::where('id', $validated['manager_user_id'])
                ->where('entreprise_id', $user->entreprise_id)
                ->first();
            if (!$manager) {
                return response()->json(['errors' => ['manager_user_id' => ['Manager invalide.']]], 422);
            }
            $manager->manager = true;
            $manager->save();
        }

        if (array_key_exists('active', $validated)) {
            $validated['active'] = (bool) $validated['active'];
        }
        $succursale->update($validated);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(Succursale $succursale)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Accès réservé au Super Admin.');
        }
        $this->ensureMultiSuccursalesEnabled($user->entreprise_id);

        if ($succursale->entreprise_id !== $user->entreprise_id) {
            abort(403);
        }

        $password = (string) request()->input('admin_password', '');
        if ($password === '' || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe Super Admin incorrect.',
            ]);
        }

        $succursale->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function exit()
    {
        session()->forget('succursale_id');
        return redirect('/dashboard');
    }

    private function ensureMultiSuccursalesEnabled(int $entrepriseId): void
    {
        $param = Parametre::where('entreprise_id', $entrepriseId)->first();
        if ($param && !$param->multi_succursales) {
            $param->multi_succursales = true;
            $param->save();
        }
    }
}
