<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TiersController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId      = auth()->user()->entreprise_id;
        $clientSearch      = trim((string) $request->get('client_search', ''));
        $fournisseurSearch = trim((string) $request->get('fournisseur_search', ''));

        // ✅ withoutGlobalScopes() contourne HasSuccursaleScope
        // Clients et fournisseurs visibles par toute l'entreprise
        $clients = Client::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when($clientSearch !== '', fn($q) => $q->where('numero_telephone', 'like', "%{$clientSearch}%"))
            ->orderBy('nom_client')
            ->get();

        $fournisseurs = Fournisseur::withoutGlobalScopes()
            ->where('entreprise_id', $entrepriseId)
            ->when($fournisseurSearch !== '', fn($q) => $q->where('nom_entreprise_fournisseur', 'like', "%{$fournisseurSearch}%"))
            ->orderBy('nom_entreprise_fournisseur')
            ->get();

        return Inertia::render('Tiers/Index', [
            'clients'      => $clients,
            'fournisseurs' => $fournisseurs,
            'filters'      => [
                'client_search'      => $clientSearch,
                'fournisseur_search' => $fournisseurSearch,
            ],
        ]);
    }
}