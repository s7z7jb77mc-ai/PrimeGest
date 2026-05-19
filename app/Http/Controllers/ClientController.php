<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $search       = trim((string) $request->get('search', ''));

    
            

        // ✅ withoutGlobalScopes() contourne HasSuccursaleScope
        // Clients visibles par toute l'entreprise — pas de filtre succursale
        $clients = Client::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when($search !== '', fn($q) => $q->where('numero_telephone', 'like', "%{$search}%"))
            ->orderBy('nom_client')
            ->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'nom_client'       => 'required|string|max:255',
            'numero_telephone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('clients', 'numero_telephone')
                    ->where('entreprise_id', $entrepriseId),
            ],
            'adresse' => 'nullable|string|max:255',
        ]);

        $validated['entreprise_id'] = $entrepriseId;

        // Succursale_id gardée pour traçabilité uniquement
        if ($succursaleId && Schema::hasColumn('clients', 'succursale_id')) {
            $validated['succursale_id'] = $succursaleId;
        }

        $client = Client::create($validated);

        $returnTo = $request->input('return_to');
        if (is_string($returnTo) && Str::startsWith($returnTo, '/')) {
            $separator = str_contains($returnTo, '?') ? '&' : '?';
            return redirect($returnTo . $separator . 'client_phone=' . urlencode($client->numero_telephone))
                ->with('success', 'Client ajouté avec succès.');
        }

        return redirect()->route('clients.index')->with('success', 'Client ajouté avec succès.');
    }

    public function update(Request $request, Client $client)
    {
        $this->authorizeClient($client);
        $this->authorize('update', $client);
        $this->assertManagerOrSuperAdmin($request);

        $entrepriseId = auth()->user()->entreprise_id;

        $validated = $request->validate([
            'nom_client'       => 'required|string|max:255',
            'numero_telephone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('clients', 'numero_telephone')
                    ->where('entreprise_id', $entrepriseId)
                    ->ignore($client->id),
            ],
            'adresse' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        $this->authorizeClient($client);
        $this->authorize('delete', $client);
        $this->assertManagerOrSuperAdmin(request());

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client supprimé avec succès.');
    }

    protected function authorizeClient(Client $client): void
    {
        if ($client->entreprise_id !== auth()->user()->entreprise_id) {
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
