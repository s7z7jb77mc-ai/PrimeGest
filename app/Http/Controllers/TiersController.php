<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

class TiersController extends Controller
{
    public function index(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $clientSearch = trim((string) $request->get('client_search', ''));
        $fournisseurSearch = trim((string) $request->get('fournisseur_search', ''));

        $clientsQuery = Client::query()->where('entreprise_id', $entrepriseId);
        if ($succursaleId && Schema::hasColumn('clients', 'succursale_id')) {
            $clientsQuery->where('succursale_id', $succursaleId);
        }
        if ($clientSearch !== '') {
            $clientsQuery->where('numero_telephone', 'like', "%{$clientSearch}%");
        }
        $clients = $clientsQuery->orderBy('nom_client')->get();

        $fournisseursQuery = Fournisseur::query()->where('entreprise_id', $entrepriseId);
        if ($succursaleId && Schema::hasColumn('fournisseurs', 'succursale_id')) {
            $fournisseursQuery->where('succursale_id', $succursaleId);
        }
        if ($fournisseurSearch !== '') {
            $fournisseursQuery->where('nom_entreprise_fournisseur', 'like', "%{$fournisseurSearch}%");
        }
        $fournisseurs = $fournisseursQuery->orderBy('nom_entreprise_fournisseur')->get();

        return Inertia::render('Tiers/Index', [
            'clients' => $clients,
            'fournisseurs' => $fournisseurs,
            'filters' => [
                'client_search' => $clientSearch,
                'fournisseur_search' => $fournisseurSearch,
            ],
        ]);
    }
}
