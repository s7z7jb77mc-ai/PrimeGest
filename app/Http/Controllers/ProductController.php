<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Stock;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    // ----------------------------------------------------------------
    // Liste des produits — filtrée par succursale si applicable
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $search       = $request->input('search');
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // ✅ Si une succursale est active, on ne retourne que les produits
        // qui ont un stock associé à cette succursale (produits connus de la succursale).
        // Au dashboard central, on retourne tous les produits de l'entreprise.
        if ($succursaleId && schema_has_column('stocks', 'succursale_id')) {
            $produitIds = Stock::where('entreprise_id', $entrepriseId)
                ->where('succursale_id', $succursaleId)
                ->pluck('produit_id');

            $produits = Produit::with(['stock' => function ($q) use ($entrepriseId, $succursaleId) {
                    $q->where('entreprise_id', $entrepriseId)
                      ->where('succursale_id', $succursaleId);
                }])
                ->where('entreprise_id', $entrepriseId)
                ->whereIn('id', $produitIds)
                ->when($search, fn($q) => $q->where('nom', 'like', "%{$search}%"))
                ->latest()
                ->get();
        } else {
            // Dashboard central : tous les produits
            $produits = Produit::with('stock')
                ->where('entreprise_id', $entrepriseId)
                ->when($search, fn($q) => $q->where('nom', 'like', "%{$search}%"))
                ->latest()
                ->get();
        }

        return Inertia::render('Produits/Index', [
            'produits' => $produits,
            'filters'  => ['search' => $search],
        ]);
    }

    // ----------------------------------------------------------------
    // Ajouter un produit
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'           => 'required|string|max:255',
            'prix_achat'    => 'required|numeric|min:0',
            'prix_vente'    => 'required|numeric|min:0',
            'seuil_stock'   => 'nullable|integer|min:0',
            'stock_initial' => 'nullable|integer|min:0',
        ]);

        $entrepriseId               = auth()->user()->entreprise_id;
        $validated['entreprise_id'] = $entrepriseId;
        $succursaleId               = session('succursale_id');

        $produit = Produit::create($validated);

        Stock::firstOrCreate(
            [
                'entreprise_id' => $entrepriseId,
                'succursale_id' => $succursaleId,
                'produit_id'    => $produit->id,
            ],
            [
                'quantite'    => (int) ($validated['stock_initial'] ?? 0),
                'prix_achat'  => $produit->prix_achat,
                'prix_vente'  => $produit->prix_vente,
                'total_achat' => 0,
                'total_vente' => 0,
                'seuil_stock' => (int) ($validated['seuil_stock'] ?? 0),
            ]
        );

        return redirect()->route('produits.index')->with('success', 'Produit ajouté avec succès.');
    }

    // ----------------------------------------------------------------
    // Créer plusieurs produits en une seule confirmation (batch)
    // ----------------------------------------------------------------

    public function storeBatch(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'admin_password'             => ['required', 'string'],
            'produits'                   => ['required', 'array', 'min:1'],
            'produits.*.nom'             => ['required', 'string', 'max:255'],
            'produits.*.prix_achat'      => ['required', 'numeric', 'min:0'],
            'produits.*.prix_vente'      => ['required', 'numeric', 'min:0'],
            'produits.*.seuil_stock'     => ['nullable', 'integer', 'min:0'],
            'produits.*.stock_initial'   => ['nullable', 'integer', 'min:0'],
        ]);

        if (!Hash::check($validated['admin_password'], $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe incorrect.',
            ]);
        }

        $entrepriseId = $user->entreprise_id;
        $succursaleId = session('succursale_id');

        DB::transaction(function () use ($validated, $entrepriseId, $succursaleId) {
            foreach ($validated['produits'] as $item) {
                $produit = Produit::create([
                    'entreprise_id' => $entrepriseId,
                    'nom'           => $item['nom'],
                    'prix_achat'    => $item['prix_achat'],
                    'prix_vente'    => $item['prix_vente'],
                ]);

                Stock::firstOrCreate(
                    [
                        'entreprise_id' => $entrepriseId,
                        'succursale_id' => $succursaleId,
                        'produit_id'    => $produit->id,
                    ],
                    [
                        'quantite'    => (int) ($item['stock_initial'] ?? 0),
                        'prix_achat'  => $produit->prix_achat,
                        'prix_vente'  => $produit->prix_vente,
                        'total_achat' => 0,
                        'total_vente' => 0,
                        'seuil_stock' => (int) ($item['seuil_stock'] ?? 0),
                    ]
                );
            }
        });

        $count = count($validated['produits']);

        return redirect()->route('produits.index')
            ->with('success', "{$count} produit(s) créé(s) avec succès.");
    }

    // ----------------------------------------------------------------
    // Modifier un produit
    // ----------------------------------------------------------------

    public function update(Request $request, Produit $produit)
    {
        $this->authorizeProduit($produit);
        $this->authorize('update', $produit);
        $this->assertManagerOrSuperAdmin($request);

        $validated = $request->validate([
            'nom'         => 'required|string|max:255',
            'prix_achat'  => 'required|numeric|min:0',
            'prix_vente'  => 'required|numeric|min:0',
            'seuil_stock' => 'nullable|integer|min:0',
        ]);

        $produit->update($validated);

        $succursaleId = session('succursale_id');
        $stock = Stock::firstOrCreate(
            [
                'entreprise_id' => $produit->entreprise_id,
                'succursale_id' => $succursaleId,
                'produit_id'    => $produit->id,
            ],
            [
                'quantite'    => 0,
                'prix_achat'  => $produit->prix_achat,
                'prix_vente'  => $produit->prix_vente,
                'total_achat' => 0,
                'total_vente' => 0,
                'seuil_stock' => 0,
            ]
        );
        $stock->seuil_stock = (int) ($validated['seuil_stock'] ?? $stock->seuil_stock ?? 0);
        $stock->save();

        return redirect()->route('produits.index')->with('success', 'Produit mis à jour avec succès.');
    }

    // ----------------------------------------------------------------
    // Supprimer un produit
    // ----------------------------------------------------------------

    public function destroy(Produit $produit)
    {
        $this->authorizeProduit($produit);
        $this->authorize('delete', $produit);
        $this->assertManagerOrSuperAdmin(request());

        $produit->delete();

        return redirect()->route('produits.index')->with('success', 'Produit supprimé avec succès.');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    protected function authorizeProduit(Produit $produit): void
    {
        if ($produit->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * ✅ Super admin OU manager de la succursale active peuvent modifier/supprimer.
     * Le manager ne peut agir que sur SA succursale.
     */
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

        // Vérification du mot de passe (super admin ou manager)
        $password = (string) $request->input('admin_password', '');
        if ($password === '' || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe incorrect.',
            ]);
        }
    }
}