<?php

namespace App\Observers;

use App\Models\Archive;
use App\Models\Journal;

class JournalObserver
{
    public function created(Journal $journal): void
    {
        $dateValue = $journal->dateHeure_operation ?? $journal->created_at;
        $dateArchive = $dateValue ? \Carbon\Carbon::parse($dateValue)->toDateString() : now()->toDateString();

        Archive::firstOrCreate(
            [
                'entreprise_id' => $journal->entreprise_id,
                'type' => 'journal',
                'reference_id' => (string) $journal->id,
            ],
            [
                'date_archive' => $dateArchive,
                'payload' => [
                    'dateHeure_operation' => $journal->dateHeure_operation,
                    'type' => $journal->type,
                    'description' => $journal->description,
                    'montant' => $journal->montant,
                    'user_id' => $journal->user_id,
                    'produit_id' => $journal->produit_id,
                ],
            ]
        );
    }
}
