<?php

namespace App\Http\Controllers;

use App\Models\Client;
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
        $succursaleId = session('succursale_id');
        $search = trim((string) $request->get('search', ''));

        $query = Client::query()->where('entreprise_id', $entrepriseId);
        if ($succursaleId && Schema::hasColumn('clients', 'succursale_id')) {
            $query->where('succursale_id', $succursaleId);
        }
        if ($search !== '') {
            $query->where('numero_telephone', 'like', "%{$search}%");
        }

        $clients = $query->orderBy('nom_client')->get();

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
            'nom_client' => 'required|string|max:255',
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
        if ($succursaleId && Schema::hasColumn('clients', 'succursale_id')) {
            $validated['succursale_id'] = $succursaleId;
        }
        $client = Client::create($validated);

        $returnTo = $request->input('return_to');
        if (is_string($returnTo) && Str::startsWith($returnTo, '/')) {
            $separator = str_contains($returnTo, '?') ? '&' : '?';
            $url = $returnTo . $separator . 'client_phone=' . urlencode($client->numero_telephone);
            return redirect($url)->with('success', 'Client ajouté avec succès.');
        }

        return redirect()->route('clients.index')->with('success', 'Client ajouté avec succès.');
    }

    public function update(Request $request, Client $client)
    {
        $this->authorizeClient($client);
        $this->authorize('update', $client);
        $this->assertSuperAdmin($request);
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $validated = $request->validate([
            'nom_client' => 'required|string|max:255',
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

        if ($succursaleId && $client->succursale_id && (int) $client->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
        $client->update($validated);

        return redirect()->route('clients.index')->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        $this->authorizeClient($client);
        $this->authorize('delete', $client);
        $this->assertSuperAdmin(request());
        $succursaleId = session('succursale_id');
        if ($succursaleId && $client->succursale_id && (int) $client->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client supprimé avec succès.');
    }

    protected function authorizeClient(Client $client): void
    {
        if ($client->entreprise_id !== auth()->user()->entreprise_id) {
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
