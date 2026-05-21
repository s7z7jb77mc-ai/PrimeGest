<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Creance;
use App\Models\Dette;
use App\Models\Caisse;
use App\Services\CaisseService;
use App\Models\Parametre;
use App\Models\Facture;
use App\Models\BonEntree;
use App\Models\ReductionUsage;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreancesDettesController extends Controller
{
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $clients = Client::where('entreprise_id', $entrepriseId)
            ->when(schema_has_column('clients', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('creance', '>', 0)
            ->orderBy('nom_client')
            ->get();

        $fournisseurs = Fournisseur::where('entreprise_id', $entrepriseId)
            ->when(schema_has_column('fournisseurs', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('dette', '>', 0)
            ->orderBy('nom_entreprise_fournisseur')
            ->get();

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';

        return Inertia::render('CreancesDettes/Index', [
            'clients' => $clients,
            'fournisseurs' => $fournisseurs,
            'devise' => $devise,
        ]);
    }

    public function payerCreance(Request $request, Client $client)
    {
        $this->authorizeClient($client);

        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
        ]);

        $montant = (float) $validated['montant'];
        if ($montant > (float) $client->creance) {
            return back()->withErrors(['montant' => 'Le montant dépasse la créance du client.']);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateOperation = now();

        $caisseId = null;
        if (schema_has_column('caisses', 'type_operation')) {
            $payload = [
                'entreprise_id' => $entrepriseId,
                'description' => 'Paiement créance: ' . $client->nom_client,
                'date_operation' => $dateOperation,
                'entree' => $montant,
                'sortie' => 0,
                'type_operation' => 'creance',
            ];
            if (schema_has_column('caisses', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }
            $caisse = CaisseService::createOperation($payload);
            $caisseId = $caisse->id;
        } else {
            $payload = [
                'entreprise_id' => $entrepriseId,
                'description' => 'Paiement créance: ' . $client->nom_client,
                'date_operation' => $dateOperation,
                'entree' => $montant,
                'sortie' => 0,
            ];
            if (schema_has_column('caisses', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }
            $caisse = CaisseService::createOperation($payload);
            $caisseId = $caisse->id;
        }

        $payload = [
            'entreprise_id' => $entrepriseId,
            'client_id' => $client->id,
            'montant_paye' => $montant,
            'caisse_id' => $caisseId,
        ];
        if (schema_has_column('creances', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        Creance::create($payload);

        $client->creance = (float) $client->creance - $montant;
        $client->save();

        return redirect()->route('creances-dettes.index')->with('success', 'Créance payée avec succès.');
    }

    public function payerDette(Request $request, Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);

        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
        ]);

        $montant = (float) $validated['montant'];
        if ($montant > (float) $fournisseur->dette) {
            return back()->withErrors(['montant' => 'Le montant dépasse la dette du fournisseur.']);
        }

        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $dateOperation = now();

        $caisseId = null;
        if (schema_has_column('caisses', 'type_operation')) {
            $payload = [
                'entreprise_id' => $entrepriseId,
                'description' => 'Paiement dette: ' . $fournisseur->nom_entreprise_fournisseur,
                'date_operation' => $dateOperation,
                'entree' => 0,
                'sortie' => $montant,
                'type_operation' => 'dette',
            ];
            if (schema_has_column('caisses', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }
            $caisse = CaisseService::createOperation($payload);
            $caisseId = $caisse->id;
        } else {
            $payload = [
                'entreprise_id' => $entrepriseId,
                'description' => 'Paiement dette: ' . $fournisseur->nom_entreprise_fournisseur,
                'date_operation' => $dateOperation,
                'entree' => 0,
                'sortie' => $montant,
            ];
            if (schema_has_column('caisses', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }
            $caisse = CaisseService::createOperation($payload);
            $caisseId = $caisse->id;
        }

        $payload = [
            'entreprise_id' => $entrepriseId,
            'fournisseur_id' => $fournisseur->id,
            'montant_paye' => $montant,
            'caisse_id' => $caisseId,
        ];
        if (schema_has_column('dettes', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        Dette::create($payload);

        $fournisseur->dette = (float) $fournisseur->dette - $montant;
        $fournisseur->save();

        return redirect()->route('creances-dettes.index')->with('success', 'Dette payée avec succès.');
    }

    protected function authorizeClient(Client $client): void
    {
        if ($client->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403);
        }
        $succursaleId = session('succursale_id');
        if ($succursaleId && $client->succursale_id && (int) $client->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
    }

    protected function authorizeFournisseur(Fournisseur $fournisseur): void
    {
        if ($fournisseur->entreprise_id !== auth()->user()->entreprise_id) {
            abort(403);
        }
        $succursaleId = session('succursale_id');
        if ($succursaleId && $fournisseur->succursale_id && (int) $fournisseur->succursale_id !== (int) $succursaleId) {
            abort(403);
        }
    }

    public function detailClient(Client $client)
    {
        $this->authorizeClient($client);
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';
        $summary = $this->buildClientMonthlySummary($entrepriseId, $client, $parametres, $succursaleId);

        return Inertia::render('CreancesDettes/Detail', [
            'entityType' => 'client',
            'entityName' => $client->nom_client,
            'entityId' => $client->id,
            'entityInfo' => [
                'telephone' => $client->numero_telephone,
                'adresse' => $client->adresse,
            ],
            'summary' => $summary,
            'devise' => $devise,
            'parametres' => $parametres,
        ]);
    }

    public function detailFournisseur(Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';
        $summary = $this->buildFournisseurMonthlySummary($entrepriseId, $fournisseur, $parametres, $succursaleId);

        return Inertia::render('CreancesDettes/Detail', [
            'entityType' => 'fournisseur',
            'entityName' => $fournisseur->nom_entreprise_fournisseur,
            'entityId' => $fournisseur->id,
            'entityInfo' => [
                'telephone' => $fournisseur->telephone ?? null,
                'adresse' => $fournisseur->adresse,
            ],
            'summary' => $summary,
            'devise' => $devise,
            'parametres' => $parametres,
        ]);
    }

    public function detailClientPdf(Request $request, Client $client)
    {
        $this->authorizeClient($client);
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';
        $summary = $this->buildClientMonthlySummary($entrepriseId, $client, $parametres, $succursaleId);

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.creances-dettes-detail', [
                'entityType' => 'client',
                'entityName' => $client->nom_client,
                'entityInfo' => [
                    'telephone' => $client->numero_telephone,
                    'adresse' => $client->adresse,
                ],
                'summary' => $summary,
                'devise' => $devise,
                'parametres' => $parametres,
            ]);

        if ($request->query('inline') === '1') {
            return $pdf->stream("creance_client_{$client->id}.pdf");
        }

        return $pdf->download("creance_client_{$client->id}.pdf");
    }

    public function detailFournisseurPdf(Request $request, Fournisseur $fournisseur)
    {
        $this->authorizeFournisseur($fournisseur);
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $parametres = Parametre::where('entreprise_id', $entrepriseId)->first();
        $devise = $parametres?->devise ?? 'CDF';
        $summary = $this->buildFournisseurMonthlySummary($entrepriseId, $fournisseur, $parametres, $succursaleId);

        $pdf = app('dompdf.wrapper')
            ->loadView('pdf.creances-dettes-detail', [
                'entityType' => 'fournisseur',
                'entityName' => $fournisseur->nom_entreprise_fournisseur,
                'entityInfo' => [
                    'telephone' => $fournisseur->telephone ?? null,
                    'adresse' => $fournisseur->adresse,
                ],
                'summary' => $summary,
                'devise' => $devise,
                'parametres' => $parametres,
            ]);

        if ($request->query('inline') === '1') {
            return $pdf->stream("dette_fournisseur_{$fournisseur->id}.pdf");
        }

        return $pdf->download("dette_fournisseur_{$fournisseur->id}.pdf");
    }

    private function buildClientOperations(int $entrepriseId, Client $client, ?int $succursaleId = null)
    {
        $credits = Facture::with('client')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('client_id', $client->id)
            ->where('statut', 'en_attente')
            ->get()
            ->map(function ($f) {
                $date = $f->date_facture ?? $f->created_at;
                return [
                    'type' => 'Vente à crédit',
                    'montant' => (float) ($f->total_ttc ?? $f->total_montant ?? 0),
                    'datetime' => $date,
                ];
            });

        $paiements = Creance::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('client_id', $client->id)
            ->get()
            ->map(function ($c) {
                return [
                    'type' => 'Paiement créance',
                    'montant' => (float) $c->montant_paye,
                    'datetime' => $c->created_at,
                ];
            });

        return $credits->merge($paiements)
            ->sortByDesc(fn($o) => Carbon::parse($o['datetime']))
            ->values();
    }

    private function buildFournisseurOperations(int $entrepriseId, Fournisseur $fournisseur, ?int $succursaleId = null)
    {
        $credits = BonEntree::with('fournisseur')
            ->where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('fournisseur_id', $fournisseur->id)
            ->where('payment_type', 'credit')
            ->get()
            ->map(function ($b) {
                $date = $b->date_bon ?? $b->created_at;
                return [
                    'type' => 'Achat à crédit',
                    'montant' => (float) ($b->total_montant ?? 0),
                    'datetime' => $date,
                ];
            });

        $paiements = Dette::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('fournisseur_id', $fournisseur->id)
            ->get()
            ->map(function ($d) {
                return [
                    'type' => 'Paiement dette',
                    'montant' => (float) $d->montant_paye,
                    'datetime' => $d->created_at,
                ];
            });

        return $credits->merge($paiements)
            ->sortByDesc(fn($o) => Carbon::parse($o['datetime']))
            ->values();
    }

    private function buildClientMonthlySummary(int $entrepriseId, Client $client, ?Parametre $parametres, ?int $succursaleId = null): array
    {
        $tauxReduction = (float) ($parametres?->reduction_accordee ?? 0);

        $facturesQuery = Facture::where('entreprise_id', $entrepriseId);
        $facturesQuery->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId));
        if (schema_has_column('factures', 'client_id')) {
            $facturesQuery->where('client_id', $client->id);
        } else {
            $facturesQuery->where('client_nom', $client->nom_client);
        }
        $factures = $facturesQuery->get();

        $paiements = Creance::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('client_id', $client->id)
            ->get();

        $usages = ReductionUsage::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->get();

        $months = collect()
            ->merge($factures->pluck('date_facture'))
            ->merge($paiements->pluck('created_at'))
            ->merge($usages->pluck('created_at'))
            ->filter()
            ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        return $months->map(function ($monthKey) use ($factures, $paiements, $usages, $tauxReduction) {
            $start = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $nextStart = (clone $start)->addMonth()->startOfMonth();
            $nextEnd = (clone $start)->addMonth()->endOfMonth();

            $facturesMois = $factures->filter(function ($f) use ($start, $end) {
                $date = $f->date_facture ?? $f->created_at;
                return $date && Carbon::parse($date)->betweenIncluded($start, $end);
            });

            $achats = $facturesMois->sum(fn($f) => (float) ($f->total_ttc ?? $f->total_montant ?? 0));
            $creances = $facturesMois
                ->filter(fn($f) => ($f->statut ?? '') === 'en_attente')
                ->sum(fn($f) => (float) ($f->total_ttc ?? $f->total_montant ?? 0));

            $paiement = $paiements->filter(function ($p) use ($start, $end) {
                return Carbon::parse($p->created_at)->betweenIncluded($start, $end);
            })->sum('montant_paye');

            $reduction = $tauxReduction > 0 ? round($achats * ($tauxReduction / 100), 2) : 0;

            $recup = $usages->filter(function ($u) use ($nextStart, $nextEnd) {
                return Carbon::parse($u->created_at)->betweenIncluded($nextStart, $nextEnd);
            })->sortBy('created_at')->first();

            return [
                'month' => $monthKey,
                'label' => $start->translatedFormat('F'),
                'achat' => $achats,
                'creance' => (float) $creances,
                'paiement' => (float) $paiement,
                'reduction' => (float) $reduction,
                'recuperation_date' => $recup ? Carbon::parse($recup->created_at)->format('d/m/Y') : '-',
            ];
        })->values()->all();
    }

    private function buildFournisseurMonthlySummary(int $entrepriseId, Fournisseur $fournisseur, ?Parametre $parametres, ?int $succursaleId = null): array
    {
        $bonsQuery = BonEntree::where('entreprise_id', $entrepriseId);
        $bonsQuery->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId));
        if (schema_has_column('bon_entrees', 'fournisseur_id')) {
            $bonsQuery->where('fournisseur_id', $fournisseur->id);
        }
        $bons = $bonsQuery->get();

        $paiements = Dette::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('fournisseur_id', $fournisseur->id)
            ->get();

        $usages = ReductionUsage::where('entreprise_id', $entrepriseId)
            ->when($succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->where('entity_type', 'fournisseur')
            ->where('entity_id', $fournisseur->id)
            ->get();

        $months = collect()
            ->merge($bons->pluck('date_bon'))
            ->merge($paiements->pluck('created_at'))
            ->merge($usages->pluck('created_at'))
            ->filter()
            ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        $taux = (float) ($fournisseur->reduction_pourcentage ?? 0);

        return $months->map(function ($monthKey) use ($bons, $paiements, $usages, $taux) {
            $start = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $nextStart = (clone $start)->addMonth()->startOfMonth();
            $nextEnd = (clone $start)->addMonth()->endOfMonth();

            $bonsMois = $bons->filter(function ($b) use ($start, $end) {
                $date = $b->date_bon ?? $b->created_at;
                return $date && Carbon::parse($date)->betweenIncluded($start, $end);
            });

            $achats = $bonsMois->sum(fn($b) => (float) ($b->total_montant ?? 0));
            $dettes = $bonsMois
                ->filter(fn($b) => ($b->payment_type ?? '') === 'credit')
                ->sum(fn($b) => (float) ($b->total_montant ?? 0));

            $paiement = $paiements->filter(function ($p) use ($start, $end) {
                return Carbon::parse($p->created_at)->betweenIncluded($start, $end);
            })->sum('montant_paye');

            $recup = $usages->filter(function ($u) use ($nextStart, $nextEnd) {
                return Carbon::parse($u->created_at)->betweenIncluded($nextStart, $nextEnd);
            })->sortBy('created_at')->first();

            return [
                'month' => $monthKey,
                'label' => $start->translatedFormat('F'),
                'achat' => $achats,
                'dette' => (float) $dettes,
                'paiement' => (float) $paiement,
                'reduction' => $taux > 0 ? round($achats * ($taux / 100), 2) : 0,
                'recuperation_date' => $recup ? Carbon::parse($recup->created_at)->format('d/m/Y') : '-',
            ];
        })->values()->all();
    }
}
