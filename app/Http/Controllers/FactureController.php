<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Produit;
use App\Models\Parametre;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FactureController extends Controller
{
    // ----------------------------------------------------------------
    // 1️⃣  Liste des factures
    // ----------------------------------------------------------------

    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $factures = Facture::with('lignes')
            ->where('entreprise_id', $entrepriseId)
            ->when(
                schema_has_column('factures', 'succursale_id') && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->orderByDesc('date_facture')
            ->orderByDesc('id')
            ->get();

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();

        return inertia('Factures/Index', compact('factures', 'parametres'));
    }

    // ----------------------------------------------------------------
    // 2️⃣  Créer une facture (formulaire simple)
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $entrepriseId = auth()->user()->entreprise_id;
            $succursaleId = session('succursale_id');

            // ✅ Numéro généré avec verrou dans la transaction
            $numero = Facture::genererNumero($entrepriseId);

            $payload = [
                'entreprise_id' => $entrepriseId,
                'user_id'       => auth()->id(),
                'numero'        => $numero,
                'cash'          => $request->cash ?? 0,
                'echange'       => $request->echange ?? 0,
                'date_facture'  => $request->date_facture ?? now(),
            ];

            if (schema_has_column('factures', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }

            $facture = Facture::create($payload);

            $totalFacture = 0;

            foreach ($request->lignes as $ligne) {
                $produit = Produit::findOrFail($ligne['produit_id']);

                $lignePayload = [
                    'facture_id'  => $facture->id,
                    'produit_id'  => $produit->id,
                    'designation' => $produit->designation,
                    'quantite'    => $ligne['quantite'],
                    'prix_ttc'    => $produit->prix_ttc,
                ];

                if (schema_has_column('facture_lignes', 'succursale_id')) {
                    $lignePayload['succursale_id'] = $succursaleId;
                }

                $factureLigne = FactureLigne::create($lignePayload);
                $totalFacture += $factureLigne->total;
            }

            $facture->update(['total_montant' => $totalFacture]);

            DB::commit();

            return redirect()->route('factures.show', $facture->id);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------------
    // 3️⃣  Afficher une facture
    // ----------------------------------------------------------------

    public function show(Facture $facture)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        if ($facture->entreprise_id !== $entrepriseId) {
            abort(403);
        }

        if ($succursaleId && $facture->succursale_id && (int) $facture->succursale_id !== (int) $succursaleId) {
            abort(403);
        }

        $facture->load('lignes.produit', 'entreprise', 'client', 'succursale');
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();

        return inertia('Factures/Show', compact('facture', 'parametres'));
    }

    // ----------------------------------------------------------------
    // 4️⃣  Archives des factures
    // ----------------------------------------------------------------

    public function archives()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $parametres  = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise      = $parametres?->devise ?? 'CDF';
        $clientPhone = trim((string) request()->get('client_phone', ''));

        $query = Facture::where('entreprise_id', $entrepriseId)
            ->when(
                schema_has_column('factures', 'succursale_id') && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            );

        if ($clientPhone !== '') {
            if (schema_has_column('factures', 'client_telephone')) {
                $query->where('client_telephone', 'like', "%{$clientPhone}%");
            } elseif (schema_has_column('factures', 'client_id')) {
                $query->whereHas('client', fn($q) => $q->where('numero_telephone', 'like', "%{$clientPhone}%"));
            }
        }

        $factures = $query
            ->orderByDesc('date_facture')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn($f) => Carbon::parse($f->date_facture)->toDateString())
            ->map(function ($items, $date) {
                return [
                    'date'           => $date,
                    'date_formatted' => Carbon::parse($date)->format('d/m/Y'),
                    'count'          => $items->count(),
                    'total_ttc'      => $items->sum(fn($f) => $f->total_ttc ?? $f->total_montant ?? 0),
                    'factures'       => $items
                        ->sortByDesc(fn($f) => $f->date_facture)
                        ->values()
                        ->map(fn($f) => [
                            'id'           => $f->id,
                            'numero'       => $f->numero,
                            'total_ttc'    => $f->total_ttc ?? $f->total_montant ?? 0,
                            'date_facture' => $f->date_facture,
                        ])
                        ->values(),
                ];
            })
            ->values();

        return inertia('Archives/Factures', [
            'factures' => $factures,
            'devise'   => $devise,
            'filters'  => ['client_phone' => $clientPhone],
        ]);
    }
}