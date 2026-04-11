<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Afficher la liste des produits
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $produits = Produit::with('stock')
            ->where('entreprise_id', auth()->user()->entreprise_id)
            ->when($search, function ($query, $search) {
                $query->where('nom', 'like', "%{$search}%");
            })
            ->latest()
            ->get();

        return Inertia::render('Produits/Index', [
            'produits' => $produits,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Ajouter un produit
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'seuil_stock' => 'nullable|integer|min:0',
        ]);

        $validated['entreprise_id'] = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $produit = Produit::create($validated);

        // Créer l'état de stock initial (quantite = 0)
        Stock::firstOrCreate(
            ['entreprise_id' => $validated['entreprise_id'], 'succursale_id' => $succursaleId, 'produit_id' => $produit->id],
            [
                'quantite' => 0,
                'prix_achat' => $produit->prix_achat,
                'prix_vente' => $produit->prix_vente,
                'total' => 0,
                'seuil_stock' => (int) ($validated['seuil_stock'] ?? 0),
            ]
        );

        return redirect()->route('produits.index')->with('success', 'Produit ajouté avec succès.');
    }


    /**
     * Modifier un produit
     */
    public function update(Request $request, Produit $produit)
    {
        $this->authorizeProduit($produit);
        $this->authorize('update', $produit);
        $this->assertSuperAdmin($request);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'seuil_stock' => 'nullable|integer|min:0',
        ]);

        $produit->update($validated);

        $succursaleId = session('succursale_id');
        $stock = Stock::firstOrCreate(
            ['entreprise_id' => $produit->entreprise_id, 'succursale_id' => $succursaleId, 'produit_id' => $produit->id],
            [
                'quantite' => 0,
                'prix_achat' => $produit->prix_achat,
                'prix_vente' => $produit->prix_vente,
                'total' => 0,
                'seuil_stock' => 0,
            ]
        );
        $stock->seuil_stock = (int) ($validated['seuil_stock'] ?? $stock->seuil_stock ?? 0);
        $stock->save();

        return redirect()->route('produits.index')->with('success', 'Produit mis à jour avec succès.');
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Produit $produit)
    {
        $this->authorizeProduit($produit);
        $this->authorize('delete', $produit);
        $this->assertSuperAdmin(request());

        $produit->delete();

        return redirect()->route('produits.index')->with('success', 'Produit supprimé avec succès.');
    }

    /**
     * Vérifie que le produit appartient à l’entreprise connectée
     */
    protected function authorizeProduit(Produit $produit)
    {
        if ($produit->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403, 'Accès non autorisé.');
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
