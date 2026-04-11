<?php

namespace App\Exports;

use App\Models\JournalArchive;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JournalExport implements FromCollection, WithHeadings, WithStyles
{
    protected $date;
    protected $entrepriseId;
    protected $succursaleId;

    public function __construct($date, $entrepriseId, ?int $succursaleId = null)
    {
        $this->date = $date;
        $this->entrepriseId = $entrepriseId;
        $this->succursaleId = $succursaleId;
    }

    public function collection()
    {
        return JournalArchive::where('entreprise_id', $this->entrepriseId)
            ->where('date_archive', $this->date)
            ->when($this->succursaleId, fn($q) => $q->where('succursale_id', $this->succursaleId))
            ->with(['produit', 'user'])
            ->orderBy('dateHeure_operation')
            ->get()
            ->map(function ($item) {
                return [
                    'Produit' => $item->produit?->nom ?? 'N/A',
                    'Type' => ucfirst($item->type),
                    'Montant' => $item->montant,
                    'Description' => $item->description,
                    'Utilisateur' => $item->user?->name ?? 'Utilisateur inconnu',
                    'Date/Heure' => $item->dateHeure_operation->format('d/m/Y H:i:s'),
                ];
            });
    }

    public function headings(): array
    {
        return ['Produit', 'Type', 'Montant', 'Description', 'Utilisateur', 'Date/Heure'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => 'center']],
        ];
    }
}
