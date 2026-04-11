<?php

namespace App\Http\Controllers;

use App\Models\Transfert;
use App\Models\Succursale;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\Archive;
use App\Models\Journal;
use App\Services\CaisseService;
use App\Services\MouvementStockService;
use App\Support\SuccursaleContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

class TransfertController extends Controller
{
    public function index()
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $succursaleId = SuccursaleContext::currentId();

        $transferts = Transfert::with([
                'fromSuccursale:id,nom,manager_user_id',
                'toSuccursale:id,nom,manager_user_id',
                'produit',
                'user'
            ])
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, function ($q) use ($succursaleId) {
                $q->where(function ($sub) use ($succursaleId) {
                    $sub->where('from_succursale_id', $succursaleId)
                        ->orWhere('to_succursale_id', $succursaleId);
                });
            })
            ->orderByDesc('date_operation')
            ->orderByDesc('id')
            ->get();
        
        $transferts->transform(function (Transfert $transfert) {
            $payload = $transfert->payload ?? [];
            $transfert->payload = $payload;
            $transfert->from_label = $transfert->fromSuccursale?->nom ?? ($payload['from_succursale'] ?? 'Central');
            $transfert->to_label = $transfert->toSuccursale?->nom ?? ($payload['to_succursale'] ?? 'Central');

            return $transfert;
        });

        $succursales = Succursale::where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $produits = Produit::where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->get(['id', 'nom', 'prix_achat', 'prix_vente']);

        return Inertia::render('Transferts/Index', [
            'transferts' => $transferts,
            'succursales' => $succursales,
            'produits' => $produits,
            'succursaleActive' => $succursaleId,
        ]);
    }

    public function storeCaisse(Request $request)
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $fromSuccursaleId = SuccursaleContext::currentId();
        if (!Succursale::where('entreprise_id', $entrepriseId)->exists()) {
            return back()->withErrors(['succursale' => 'Aucune succursale disponible pour le transfert.']);
        }

        $validated = $request->validate([
            'to_succursale_id' => 'required|integer',
            'montant' => 'required|numeric|min:0.01',
            'date_operation' => 'nullable|date',
        ]);

        if ($fromSuccursaleId && (int) $validated['to_succursale_id'] === $fromSuccursaleId) {
            return back()->withErrors(['to_succursale_id' => 'La succursale de destination doit être différente.']);
        }

        $toSuccursaleId = Succursale::where('entreprise_id', $entrepriseId)
            ->whereKey((int) $validated['to_succursale_id'])
            ->value('id');
        if (!$toSuccursaleId) {
            return back()->withErrors(['to_succursale_id' => 'Succursale de destination invalide.']);
        }
        $montant = (float) $validated['montant'];
        $dateOperation = $validated['date_operation'] ?? now();

        $fromSuccursale = $fromSuccursaleId ? Succursale::find($fromSuccursaleId) : null;
        $toSuccursale = Succursale::find($toSuccursaleId);

        Transfert::create([
            'entreprise_id' => $entrepriseId,
            'from_succursale_id' => $fromSuccursaleId,
            'to_succursale_id' => $toSuccursaleId,
            'user_id' => Auth::id(),
            'type' => 'caisse',
            'montant' => $montant,
            'status' => 'pending',
            'date_operation' => $dateOperation,
            'payload' => [
                'type' => 'caisse',
                'from_succursale' => $fromSuccursale?->nom ?? 'Central',
                'to_succursale' => $toSuccursale?->nom,
                'montant' => $montant,
                'date_operation' => $dateOperation,
                'user_id' => Auth::id(),
            ],
        ]);

        return redirect()->route('transferts.index')->with('success', 'Demande de transfert de caisse enregistrée.');
    }

    public function storeStock(Request $request)
    {
        $entrepriseId = Auth::user()->entreprise_id;
        $fromSuccursaleId = SuccursaleContext::currentId();
        if (!Succursale::where('entreprise_id', $entrepriseId)->exists()) {
            return back()->withErrors(['succursale' => 'Aucune succursale disponible pour le transfert.']);
        }

        $validated = $request->validate([
            'to_succursale_id' => 'required|integer',
            'produit_id' => 'required|integer',
            'quantite' => 'required|numeric|min:0.01',
            'date_operation' => 'nullable|date',
        ]);

        if ($fromSuccursaleId && (int) $validated['to_succursale_id'] === $fromSuccursaleId) {
            return back()->withErrors(['to_succursale_id' => 'La succursale de destination doit être différente.']);
        }

        $toSuccursaleId = Succursale::where('entreprise_id', $entrepriseId)
            ->whereKey((int) $validated['to_succursale_id'])
            ->value('id');
        if (!$toSuccursaleId) {
            return back()->withErrors(['to_succursale_id' => 'Succursale de destination invalide.']);
        }
        $produit = Produit::where('entreprise_id', $entrepriseId)->findOrFail($validated['produit_id']);
        $quantite = (float) $validated['quantite'];
        $dateOperation = $validated['date_operation'] ?? now();
        $fromSuccursale = $fromSuccursaleId ? Succursale::find($fromSuccursaleId) : null;
        $toSuccursale = Succursale::find($toSuccursaleId);
        Transfert::create([
            'entreprise_id' => $entrepriseId,
            'from_succursale_id' => $fromSuccursaleId,
            'to_succursale_id' => $toSuccursaleId,
            'produit_id' => $produit->id,
            'user_id' => Auth::id(),
            'type' => 'stock',
            'quantite' => $quantite,
            'status' => 'pending',
            'date_operation' => $dateOperation,
            'payload' => [
                'type' => 'stock',
                'from_succursale' => $fromSuccursale?->nom ?? 'Central',
                'to_succursale' => $toSuccursale?->nom,
                'produit_id' => $produit->id,
                'produit' => $produit->nom,
                'quantite' => $quantite,
                'date_operation' => $dateOperation,
                'user_id' => Auth::id(),
            ],
        ]);

        return redirect()->route('transferts.index')->with('success', 'Demande de transfert de stock enregistrée.');
    }

    public function approve(Request $request, Transfert $transfert)
    {
        $user = Auth::user();
        if ($transfert->entreprise_id !== $user->entreprise_id) {
            abort(403);
        }
        if ($transfert->status !== 'pending') {
            return back()->withErrors(['status' => 'Ce transfert est déjà traité.']);
        }

        $validated = $request->validate([
            'admin_password' => 'required|string',
        ]);
        $this->assertTransferPermission($transfert->from_succursale_id, $validated['admin_password']);

        DB::transaction(function () use ($transfert, $user) {
            if ($transfert->type === 'caisse') {
                $this->executeCaisseTransfer($transfert);
            } else {
                $this->executeStockTransfer($transfert);
            }

            $transfert->status = 'validated';
            $transfert->approved_by = $user->id;
            $transfert->approved_at = now();
            $transfert->save();
        });

        return redirect()->route('transferts.index')->with('success', 'Transfert validé.');
    }

    public function reject(Request $request, Transfert $transfert)
    {
        $user = Auth::user();
        if ($transfert->entreprise_id !== $user->entreprise_id) {
            abort(403);
        }
        if ($transfert->status !== 'pending') {
            return back()->withErrors(['status' => 'Ce transfert est déjà traité.']);
        }

        $validated = $request->validate([
            'admin_password' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);
        $this->assertTransferPermission($transfert->from_succursale_id, $validated['admin_password']);

        $payload = $transfert->payload ?? [];
        if (!empty($validated['reason'])) {
            $payload['rejection_reason'] = $validated['reason'];
        }

        $transfert->status = 'rejected';
        $transfert->approved_by = $user->id;
        $transfert->approved_at = now();
        $transfert->payload = $payload;
        $transfert->save();

        return redirect()->route('transferts.index')->with('success', 'Transfert rejeté.');
    }

    private function executeCaisseTransfer(Transfert $transfert): void
    {
        $entrepriseId = $transfert->entreprise_id;
        $fromSuccursaleId = $transfert->from_succursale_id;
        $toSuccursaleId = $transfert->to_succursale_id;
        $montant = (float) $transfert->montant;
        $dateOperation = $transfert->date_operation ?? now();

        $fromSuccursale = Succursale::find($fromSuccursaleId);
        $toSuccursale = Succursale::find($toSuccursaleId);
        $fromLabel = $fromSuccursale?->nom ?? 'Central';
        $toLabel = $toSuccursale?->nom ?? 'Central';

        $caisseOut = CaisseService::createOperation([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $fromSuccursaleId,
            'description' => 'Transfert de fonds vers ' . $toLabel,
            'date_operation' => $dateOperation,
            'entree' => 0,
            'sortie' => $montant,
            'type_operation' => 'transfert_out',
        ]);

        $caisseIn = CaisseService::createOperation([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $toSuccursaleId,
            'description' => 'Transfert de fonds provenant de ' . $fromLabel,
            'date_operation' => $dateOperation,
            'entree' => $montant,
            'sortie' => 0,
            'type_operation' => 'transfert_in',
        ]);

        SuccursaleContext::withoutScope(Journal::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $fromSuccursaleId,
            'dateHeure_operation' => $dateOperation,
            'type' => MouvementStockService::normalizeType('sortie'),
            'description' => 'Transfert caisse envoye vers ' . $toLabel,
            'montant' => $montant,
            'user_id' => Auth::id(),
        ]);

        SuccursaleContext::withoutScope(Journal::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $toSuccursaleId,
            'dateHeure_operation' => $dateOperation,
            'type' => MouvementStockService::normalizeType('entree'),
            'description' => 'Transfert caisse recu de ' . $fromLabel,
            'montant' => $montant,
            'user_id' => Auth::id(),
        ]);

        $payload = [
            'type' => 'caisse',
            'from_succursale' => $fromLabel,
            'to_succursale' => $toLabel,
            'montant' => $montant,
            'date_operation' => $dateOperation,
            'user_id' => Auth::id(),
            'status' => 'validated',
        ];
        $archiveData = [
            'entreprise_id' => $entrepriseId,
            'type' => 'transfert',
            'date_archive' => $dateOperation instanceof \DateTimeInterface ? $dateOperation->format('Y-m-d') : now()->toDateString(),
            'payload' => $payload,
        ];
        if (Schema::hasColumn('archives', 'succursale_id')) {
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'succursale_id' => $fromSuccursaleId,
                'reference_id' => (string) $caisseOut->id,
            ]));
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'succursale_id' => $toSuccursaleId,
                'reference_id' => (string) $caisseIn->id,
            ]));
        } else {
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'reference_id' => (string) $caisseOut->id,
            ]));
        }

        $payloadMeta = $transfert->payload ?? [];
        $payloadMeta['caisse_out_id'] = $caisseOut->id;
        $payloadMeta['caisse_in_id'] = $caisseIn->id;
        $transfert->payload = $payloadMeta;
        $transfert->save();
    }

    private function executeStockTransfer(Transfert $transfert): void
    {
        $entrepriseId = $transfert->entreprise_id;
        $fromSuccursaleId = $transfert->from_succursale_id;
        $toSuccursaleId = $transfert->to_succursale_id;
        $quantite = (float) $transfert->quantite;
        $dateOperation = $transfert->date_operation ?? now();

        $produit = Produit::where('entreprise_id', $entrepriseId)->findOrFail($transfert->produit_id);
        $prixUnitaire = (float) ($produit->prix_achat ?? $produit->prix_vente ?? 0);

        $stockFrom = SuccursaleContext::withoutScope(Stock::class)->firstOrCreate(
            ['entreprise_id' => $entrepriseId, 'succursale_id' => $fromSuccursaleId, 'produit_id' => $produit->id],
            ['quantite' => 0, 'prix_achat' => $produit->prix_achat, 'prix_vente' => $produit->prix_vente, 'total_achat' => 0, 'total_vente' => 0]
        );

        if ((float) $stockFrom->quantite < $quantite) {
            throw ValidationException::withMessages(['quantite' => 'Stock insuffisant dans la succursale source.']);
        }

        $stockTo = SuccursaleContext::withoutScope(Stock::class)->firstOrCreate(
            ['entreprise_id' => $entrepriseId, 'succursale_id' => $toSuccursaleId, 'produit_id' => $produit->id],
            ['quantite' => 0, 'prix_achat' => $produit->prix_achat, 'prix_vente' => $produit->prix_vente, 'total_achat' => 0, 'total_vente' => 0]
        );

        $mouvementOut = SuccursaleContext::withoutScope(MouvementStock::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $fromSuccursaleId,
            'produit_id' => $produit->id,
            'type' => 'sortie',
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'prix_total' => $quantite * $prixUnitaire,
            'user_id' => Auth::id(),
            'commentaire' => 'Transfert stock vers succursale',
            'payment_type' => 'transfer',

        ]);

        $mouvementIn = SuccursaleContext::withoutScope(MouvementStock::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $toSuccursaleId,
            'produit_id' => $produit->id,
            'type' => 'entree',
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'prix_total' => $quantite * $prixUnitaire,
            'user_id' => Auth::id(),
            'commentaire' => 'Transfert stock depuis succursale',
            'payment_type' => 'transfer',

        ]);

        $stockFrom->quantite = (float) $stockFrom->quantite - $quantite;
        $stockFrom->total_achat = (float) $stockFrom->quantite * (float) ($stockFrom->prix_achat ?? $prixUnitaire);
        $stockFrom->total_vente = (float) $stockFrom->quantite * (float) ($stockFrom->prix_vente ?? $produit->prix_vente ?? 0);
        $stockFrom->save();

        $stockTo->quantite = (float) $stockTo->quantite + $quantite;
        $stockTo->total_achat = (float) $stockTo->quantite * (float) ($stockTo->prix_achat ?? $prixUnitaire);
        $stockTo->total_vente = (float) $stockTo->quantite * (float) ($stockTo->prix_vente ?? $produit->prix_vente ?? 0);
        $stockTo->save();

        $fromSuccursale = Succursale::find($fromSuccursaleId);
        $toSuccursale = Succursale::find($toSuccursaleId);
        $fromLabel = $fromSuccursale?->nom ?? 'Central';
        $toLabel = $toSuccursale?->nom ?? 'Central';

        SuccursaleContext::withoutScope(Journal::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $fromSuccursaleId,
            'produit_id' => $produit->id,
            'dateHeure_operation' => $dateOperation,
            'type' => 'sortie',
            'description' => 'Transfert stock envoye vers ' . $toLabel . ' - ' . $produit->nom,
            'montant' => 0,
            'user_id' => Auth::id(),
        ]);

        SuccursaleContext::withoutScope(Journal::class)->create([
            'entreprise_id' => $entrepriseId,
            'succursale_id' => $toSuccursaleId,
            'produit_id' => $produit->id,
            'dateHeure_operation' => $dateOperation,
            'type' => 'entree',
            'description' => 'Transfert stock recu de ' . $fromLabel . ' - ' . $produit->nom,
            'montant' => 0,
            'user_id' => Auth::id(),
        ]);

        $payload = [
            'type' => 'stock',
            'from_succursale' => $fromLabel,
            'to_succursale' => $toLabel,
            'produit' => $produit->nom,
            'quantite' => $quantite,
            'date_operation' => $dateOperation,
            'user_id' => Auth::id(),
            'status' => 'validated',
        ];
        $archiveData = [
            'entreprise_id' => $entrepriseId,
            'type' => 'transfert',
            'date_archive' => $dateOperation instanceof \DateTimeInterface ? $dateOperation->format('Y-m-d') : now()->toDateString(),
            'payload' => $payload,
        ];
        if (Schema::hasColumn('archives', 'succursale_id')) {
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'succursale_id' => $fromSuccursaleId,
                'reference_id' => (string) $mouvementOut->id,
            ]));
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'succursale_id' => $toSuccursaleId,
                'reference_id' => (string) $mouvementIn->id,
            ]));
        } else {
            SuccursaleContext::withoutScope(Archive::class)->create(array_merge($archiveData, [
                'reference_id' => (string) $mouvementOut->id,
            ]));
        }

        $payloadMeta = $transfert->payload ?? [];
        $payloadMeta['mouvement_out_id'] = $mouvementOut->id;
        $payloadMeta['mouvement_in_id'] = $mouvementIn->id;
        $transfert->payload = $payloadMeta;
        $transfert->save();
    }

    private function assertTransferPermission(?int $fromSuccursaleId, string $password): void
    {
        $user = Auth::user();
        $succursale = null;
        if ($fromSuccursaleId !== null) {
            $succursale = Succursale::where('entreprise_id', $user->entreprise_id)
                ->where('id', $fromSuccursaleId)
                ->first();
        }

        $allowed = $user->isSuperAdmin() || ($succursale && (int) $succursale->manager_user_id === (int) $user->id);
        if (!$allowed) {
            abort(403, 'Accès réservé au Super Admin ou au manager de la succursale.');
        }

        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'Mot de passe Super Admin/Manager incorrect.',
            ]);
        }
    }
}
