<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\Archive;
use App\Support\SuccursaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaisseService
{
    public static function createOperation(array $data): Caisse
    {
        return DB::transaction(function () use ($data) {
            $entrepriseId = $data['entreprise_id'];
            $succursaleId = $data['succursale_id'] ?? null;
            $entree = (float) ($data['entree'] ?? 0);
            $sortie = (float) ($data['sortie'] ?? 0);

            $current = (float) SuccursaleContext::withoutScope(Caisse::class)
                ->where('entreprise_id', $entrepriseId)
                ->when(
                    SuccursaleContext::hasColumn('caisses'),
                    fn($q) => $succursaleId !== null
                        ? $q->where('succursale_id', $succursaleId)
                        : $q->whereNull('succursale_id')
                )
                ->lockForUpdate()
                ->selectRaw('COALESCE(SUM(entree - sortie), 0) as solde')
                ->value('solde');

            $newSolde = $current + $entree - $sortie;
            if ($newSolde < 0) {
                throw ValidationException::withMessages([
                    'caisse' => 'Solde de caisse insuffisant',
                ]);
            }

            $data['solde'] = $newSolde;

            $caisse = SuccursaleContext::withoutScope(Caisse::class)->create($data);

            if (schema_has_table('archives')) {
                $dateArchive = $caisse->date_operation?->toDateString() ?? $caisse->created_at->toDateString();
                $archiveWhere = [
                    'entreprise_id' => $caisse->entreprise_id,
                    'type' => 'caisse',
                    'reference_id' => (string) $caisse->id,
                ];
                $archiveData = [
                    'date_archive' => $dateArchive,
                    'payload' => [
                        'date_operation' => $caisse->date_operation ?? $caisse->created_at,
                        'description' => $caisse->description,
                        'entree' => $caisse->entree,
                        'sortie' => $caisse->sortie,
                        'solde' => $caisse->solde,
                        'type_operation' => $caisse->type_operation ?? null,
                    ],
                ];
                if (schema_has_column('archives', 'succursale_id')) {
                    $archiveWhere['succursale_id'] = $succursaleId;
                }
                SuccursaleContext::withoutScope(Archive::class)->firstOrCreate($archiveWhere, $archiveData);
            }

            return $caisse;
        });
    }
}
