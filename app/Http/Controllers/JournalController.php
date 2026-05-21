<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Succursale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Caisse;
use App\Services\CaisseService;

class JournalController extends Controller
{
    // ----------------------------------------------------------------
    // Liste des opérations du jour
    // ----------------------------------------------------------------

    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $today        = now()->toDateString();

        $journals = Journal::where('entreprise_id', $entrepriseId)
            ->when(
                schema_has_column('journals', 'succursale_id') && $succursaleId,
                fn($q) => $q->where('succursale_id', $succursaleId)
            )
            ->whereDate('dateHeure_operation', $today)
            ->orderBy('dateHeure_operation', 'desc')
            ->get();

        // ✅ Au dashboard central (pas de succursale active), préfixer
        // chaque description par le nom de la succursale concernée.
        if (!$succursaleId && schema_has_column('journals', 'succursale_id')) {
            // Charger les noms de succursales en une seule requête
            $succursaleIds = $journals->pluck('succursale_id')->filter()->unique()->values();
            $succursales   = Succursale::whereIn('id', $succursaleIds)
                ->where('entreprise_id', $entrepriseId)
                ->pluck('nom', 'id'); // [id => nom]

            $journals = $journals->map(function ($j) use ($succursales) {
                if ($j->succursale_id && isset($succursales[$j->succursale_id])) {
                    $j->description = '[' . $succursales[$j->succursale_id] . '] ' . ($j->description ?? '');
                }
                return $j;
            });
        }

        return Inertia::render('Journal/Index', [
            'journals' => $journals->values(),
        ]);
    }

    // ----------------------------------------------------------------
    // Créer une entrée de journal
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $data = $request->validate([
            'dateHeure_operation' => 'nullable|string',
            'type'                => 'required|in:entree,sortie',
            'description'         => 'nullable|string|max:500',
            'montant'             => 'required|numeric',
            'produit_id'          => 'nullable|integer',
        ]);

        try {
            DB::beginTransaction();

            // Normalisation de la date
            $dt      = trim((string) ($data['dateHeure_operation'] ?? now()->toDateTimeString()));
            $dtForDb = null;
            $formats = [
                'Y-m-d H:i:s', 'Y-m-d H:i',
                'Y-m-d\TH:i:s', 'Y-m-d\TH:i',
                'd/m/Y H:i:s', 'd/m/Y H:i',
            ];
            foreach ($formats as $fmt) {
                $tmp = \DateTime::createFromFormat($fmt, $dt);
                if ($tmp) {
                    $errors = \DateTime::getLastErrors();
                    if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                        $dtForDb = \Carbon\Carbon::instance($tmp)->toDateTimeString();
                        break;
                    }
                }
            }
            if (!$dtForDb) {
                try {
                    $dtForDb = \Carbon\Carbon::parse($dt)->toDateTimeString();
                } catch (\Throwable $e) {
                    $dtForDb = now()->toDateTimeString();
                }
            }

            // ✅ Préfixer la description par le nom de la succursale
            // pour que les opérations soient identifiables depuis le central
            $description = $data['description'] ?? null;
            if ($succursaleId && schema_has_column('journals', 'succursale_id')) {
                $nomSucc = Succursale::where('id', $succursaleId)
                    ->where('entreprise_id', $entrepriseId)
                    ->value('nom');
                if ($nomSucc && $description) {
                    $description = '[' . $nomSucc . '] ' . $description;
                } elseif ($nomSucc && !$description) {
                    $description = '[' . $nomSucc . '] ' . ucfirst($data['type']);
                }
            }

            $payload = [
                'entreprise_id'       => $entrepriseId,
                'produit_id'          => $data['produit_id'] ?? null,
                'dateHeure_operation' => $dtForDb,
                'type'                => $data['type'],
                'description'         => $description,
                'montant'             => $data['montant'],
            ];
            if (schema_has_column('journals', 'succursale_id')) {
                $payload['succursale_id'] = $succursaleId;
            }
            if (schema_has_column('journals', 'user_id')) {
                $payload['user_id'] = auth()->id();
            }

            $journal = Journal::create($payload);

            // Impacter la caisse
            if (in_array($data['type'], ['entree', 'sortie'], true)) {
                $entree    = $data['type'] === 'entree' ? (float) $data['montant'] : 0;
                $sortie    = $data['type'] === 'sortie' ? (float) $data['montant'] : 0;
                $caisseData = [
                    'entreprise_id'  => $entrepriseId,
                    'description'    => 'Journal: ' . ($description ?? $data['type']),
                    'date_operation' => $dtForDb,
                    'entree'         => $entree,
                    'sortie'         => $sortie,
                ];
                if (schema_has_column('caisses', 'succursale_id')) {
                    $caisseData['succursale_id'] = $succursaleId;
                }
                if (schema_has_column('caisses', 'type_operation')) {
                    $caisseData['type_operation'] = 'journal';
                }
                CaisseService::createOperation($caisseData);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Opération ajoutée.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Erreur création journal: ' . $e->getMessage());
            return back()->withErrors([
                'server' => 'Erreur serveur lors de la création de l\'opération : ' . $e->getMessage()
            ])->withInput();
        }
    }

    // ----------------------------------------------------------------
    // Méthode statique utilitaire
    // ----------------------------------------------------------------

    public static function add($type, $description, $montant, $produit_id = null)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        // ✅ Préfixer par le nom de la succursale si applicable
        if ($succursaleId && schema_has_column('journals', 'succursale_id')) {
            $nomSucc = Succursale::where('id', $succursaleId)
                ->where('entreprise_id', $entrepriseId)
                ->value('nom');
            if ($nomSucc && $description) {
                $description = '[' . $nomSucc . '] ' . $description;
            }
        }

        $payload = [
            'entreprise_id'       => $entrepriseId,
            'produit_id'          => $produit_id,
            'dateHeure_operation' => now()->toDateTimeString(),
            'type'                => $type,
            'description'         => $description,
            'montant'             => $montant,
        ];
        if (schema_has_column('journals', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        return Journal::create($payload);
    }
}