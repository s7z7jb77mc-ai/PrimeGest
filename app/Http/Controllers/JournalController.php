<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Caisse;
use App\Services\CaisseService;

class JournalController extends Controller
{
    // Afficher toutes les opérations du journal DU JOUR uniquement
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');
        $today = now()->toDateString();

        $journals = Journal::where('entreprise_id', $entrepriseId)
            ->when(Schema::hasColumn('journals', 'succursale_id') && $succursaleId, fn($q) => $q->where('succursale_id', $succursaleId))
            ->whereDate('dateHeure_operation', $today)
            ->orderBy('dateHeure_operation', 'desc')
            ->get();

        return Inertia::render('Journal/Index', [
            'journals' => $journals,
        ]);
    }

    /**
     * Store a newly created journal entry (pour le formulaire /journals POST).
     */
   public function store(Request $request)
{
    $entrepriseId = auth()->user()->entreprise_id;
    $succursaleId = session('succursale_id');

    $data = $request->validate([
        'dateHeure_operation' => 'nullable|string',
        'type' => 'required|in:entree,sortie',
        'description' => 'nullable|string|max:500',
        'montant' => 'required|numeric',
        'produit_id' => 'nullable|integer',
    ]);

    try {
        DB::beginTransaction();

        $dt = $data['dateHeure_operation'] ?? now()->toDateTimeString();
        $dt = trim((string) $dt);
        $dtForDb = null;
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
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

        $payload = [
            'entreprise_id'       => $entrepriseId,
            'produit_id'          => $data['produit_id'] ?? null,
            'dateHeure_operation' => $dtForDb,
            'type'                => $data['type'],
            'description'         => $data['description'] ?? null,
            'montant'             => $data['montant'],
        ];
        if (Schema::hasColumn('journals', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        if (Schema::hasColumn('journals', 'user_id')) {
            $payload['user_id'] = auth()->id();
        }

        $journal = Journal::create($payload);

        // Impacter la caisse si entrée/sortie
        if (in_array($data['type'], ['entree', 'sortie'], true)) {
            $entree = $data['type'] === 'entree' ? (float) $data['montant'] : 0;
            $sortie = $data['type'] === 'sortie' ? (float) $data['montant'] : 0;
            $caisseData = [
                'entreprise_id' => $entrepriseId,
                'description' => 'Journal: ' . ($data['description'] ?? $data['type']),
                'date_operation' => $dtForDb,
                'entree' => $entree,
                'sortie' => $sortie,
            ];
            if (Schema::hasColumn('caisses', 'succursale_id')) {
                $caisseData['succursale_id'] = $succursaleId;
            }
            if (Schema::hasColumn('caisses', 'type_operation')) {
                $caisseData['type_operation'] = 'journal';
            }
            CaisseService::createOperation($caisseData);
        }

        DB::commit();

        return redirect()->back()->with('success', 'Opération ajoutée.');
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Erreur création journal: '.$e->getMessage());
        return back()->withErrors([
            'server' => 'Erreur serveur lors de la création de l\'opération : '.$e->getMessage()
        ])->withInput();
    }
}

    // Méthode statique pour ajouter une entrée au journal depuis n'importe où dans l'application
    public static function add($type, $description, $montant, $produit_id = null)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $succursaleId = session('succursale_id');

        $payload = [
            'entreprise_id'      => $entrepriseId,
            'produit_id'         => $produit_id,
            'dateHeure_operation'=> now()->toDateTimeString(),
            'type'               => $type,
            'description'        => $description,
            'montant'            => $montant,
        ];
        if (Schema::hasColumn('journals', 'succursale_id')) {
            $payload['succursale_id'] = $succursaleId;
        }
        return Journal::create($payload);
    }
}
