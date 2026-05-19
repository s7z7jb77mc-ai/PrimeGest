<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Succursale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ParametreController extends Controller
{
    public function index()
    {
        $entrepriseId = Auth::user()?->entreprise_id;
        $parametre = Parametre::where('entreprise_id', $entrepriseId)->first();
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

        if ($parametre && $succursales->isNotEmpty() && !$parametre->multi_succursales) {
            $parametre->multi_succursales = true;
            $parametre->save();
        }

        return inertia('Parametre/Index', [
            'parametre' => $parametre,
            'managers' => $managers,
            'succursales' => $succursales,
        ]);
    }

    public function store(Request $request)
    {
        try {
            if ($request->has('multi_succursales')) {
                $raw = $request->input('multi_succursales');
                $request->merge([
                    'multi_succursales' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
            $validated = $request->validate([
                'nom_entreprise' => 'required|string|max:255',
                'adresse' => 'nullable|string',
                'email' => 'nullable|email',
                'telephone' => 'nullable|string',
                'devise' => 'required|in:USD,CDF,RWF',
                'langue' => 'nullable|in:fr,en',
                'rccm' => 'nullable|string',
                'identifiant_national' => 'nullable|string',
                'numero_impot' => 'nullable|string',
                'tva' => 'nullable|numeric|min:0',
                'reduction_accordee' => 'nullable|numeric|min:0|max:100',
                'message_remerciement' => 'nullable|string',
                'multi_succursales' => 'nullable|boolean',
                'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:5120',
                'logo_position' => 'nullable|in:left,center,right',
                'superPassword' => 'required|string',
            ]);

            // Récupérer l'utilisateur actuel (qui doit être un administrateur d'entreprise)
            $currentUser = Auth::user();
            $entreprise = $currentUser->entreprise;

            // Récupérer l'administrateur qui a créé l'entreprise
            $admin = User::where('id', $currentUser->id)
                         ->orWhere('id', $entreprise?->user_id)
                         ->first();

            if (!$admin || !Hash::check($validated['superPassword'], $admin->password)) {
                return response()->json(['errors' => ['superPassword' => ['Mot de passe super admin incorrect !']]], 422);
            }

            unset($validated['superPassword']);
            $validated['multi_succursales'] = (bool) ($validated['multi_succursales'] ?? false);
            if (!$validated['multi_succursales']) {
                $hasSuccursales = Succursale::where('entreprise_id', $currentUser->entreprise_id)->exists();
                if ($hasSuccursales) {
                    $validated['multi_succursales'] = true;
                }
            }

            // Lier à l'entreprise de l'utilisateur connecté
            $validated['entreprise_id'] = $currentUser->entreprise_id;
            if (empty($validated['nom_entreprise'])) {
                $validated['nom_entreprise'] = $entreprise?->name ?? $currentUser->name;
            }

            $this->storeLogo($request, $validated);
            if ($request->input('logo_selected') === '1' && !$request->hasFile('logo')) {
                return response()->json([
                    'errors' => ['logo' => ['Le fichier logo n’a pas été reçu par le serveur. Essayez un fichier plus petit.']]
                ], 422);
            }
            if (!Schema::hasColumn('parametres', 'logo_position')) {
                unset($validated['logo_position']);
            }

            // Met à jour ou crée
            $parametre = \App\Models\Parametre::where('entreprise_id', $currentUser->entreprise_id)->first();
            if ($parametre) {
                $parametre->update($validated);
            } else {
                $parametre = \App\Models\Parametre::create($validated);
            }

            return response()->json([
                'success' => '✅ Paramètres enregistrés avec succès.',
                'parametre' => $parametre->fresh(),
                'logo_url' => $parametre->fresh()?->logo_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur ParametreController::store', ['exception' => $e->getMessage()]);
            return response()->json(['errors' => ['general' => [$e->getMessage()]]], 500);
        }
    }

    public function update(Request $request, Parametre $parametre)
    {
        try {
            $this->authorize('update', $parametre);
            $currentUser = Auth::user();
            if ($parametre->entreprise_id !== $currentUser->entreprise_id) {
                return response()->json(['errors' => ['general' => ['Accès non autorisé.']]], 403);
            }

            if ($request->has('multi_succursales')) {
                $raw = $request->input('multi_succursales');
                $request->merge([
                    'multi_succursales' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
            $validated = $request->validate([
                'nom_entreprise' => 'required|string|max:255',
                'adresse' => 'nullable|string',
                'email' => 'nullable|email',
                'telephone' => 'nullable|string',
                'devise' => 'required|in:USD,CDF,RWF',
                'langue' => 'nullable|in:fr,en',
                'rccm' => 'nullable|string',
                'identifiant_national' => 'nullable|string',
                'numero_impot' => 'nullable|string',
                'tva' => 'nullable|numeric|min:0',
                'reduction_accordee' => 'nullable|numeric|min:0|max:100',
                'message_remerciement' => 'nullable|string',
                'multi_succursales' => 'nullable|boolean',
                'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:5120',
                'logo_position' => 'nullable|in:left,center,right',
                'superPassword' => 'required|string',
            ]);

            // Récupérer l'utilisateur actuel (qui doit être un administrateur d'entreprise)
            $entreprise = $currentUser->entreprise;

            // Récupérer l'administrateur qui a créé l'entreprise
            $admin = User::where('id', $currentUser->id)
                         ->orWhere('id', $entreprise?->user_id)
                         ->first();

            if (!$admin || !Hash::check($validated['superPassword'], $admin->password)) {
                return response()->json(['errors' => ['superPassword' => ['Mot de passe super admin incorrect !']]], 422);
            }

            unset($validated['superPassword']);
            $validated['multi_succursales'] = (bool) ($validated['multi_succursales'] ?? false);
            if (!$validated['multi_succursales']) {
                $hasSuccursales = Succursale::where('entreprise_id', $currentUser->entreprise_id)->exists();
                if ($hasSuccursales) {
                    $validated['multi_succursales'] = true;
                }
            }

            // Mise à jour des paramètres
            if (empty($validated['nom_entreprise'])) {
                $validated['nom_entreprise'] = $entreprise?->name ?? $currentUser->name;
            }

            $this->storeLogo($request, $validated);
            if ($request->input('logo_selected') === '1' && !$request->hasFile('logo')) {
                return response()->json([
                    'errors' => ['logo' => ['Le fichier logo n’a pas été reçu par le serveur. Essayez un fichier plus petit.']]
                ], 422);
            }
            if (!Schema::hasColumn('parametres', 'logo_position')) {
                unset($validated['logo_position']);
            }
            $parametre->update($validated);

            return response()->json([
                'success' => '✅ Paramètres mis à jour avec succès.',
                'parametre' => $parametre->fresh(),
                'logo_url' => $parametre->fresh()?->logo_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur ParametreController::update', ['exception' => $e->getMessage()]);
            return response()->json(['errors' => ['general' => [$e->getMessage()]]], 500);
        }
    }

    private function storeLogo(Request $request, array &$validated): void
    {
        if (!$request->hasFile('logo')) {
            return;
        }

        $file = $request->file('logo');
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $destination = public_path('logos');

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $file->move($destination, $filename);
        $path = 'logos/' . $filename;

        if (Schema::hasColumn('parametres', 'logo_path')) {
            $validated['logo_path'] = $path;
        }
        if (Schema::hasColumn('parametres', 'logo')) {
            $validated['logo'] = $path;
        }
    }
}
