<?php

namespace App\Exports;

use App\Models\MouvementStockArchive;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MouvementStockExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $date;
    protected $succursaleId;

    public function __construct($date, ?int $succursaleId = null)
    {
        $this->date = $date;
        $this->succursaleId = $succursaleId;
    }

    public function collection()
    {
        return MouvementStockArchive::with('produit', 'user')
            ->whereDate('date_archive', $this->date)
            ->where('entreprise_id', auth()->user()->entreprise_id)
            ->when($this->succursaleId, fn($q) => $q->where('succursale_id', $this->succursaleId))
            ->get()
            ->map(function ($mouvement) {
                return [
                    'produit' => $mouvement->produit->nom ?? 'N/A',
                    'type' => $mouvement->type === 'entree' ? 'Entrée' : 'Sortie',
                    'quantite' => $mouvement->quantite,
                    'prix_unitaire' => $mouvement->prix_unitaire,
                    'prix_total' => $mouvement->prix_total,
                    'utilisateur' => $mouvement->user->name ?? 'Inconnu',
                    'commentaire' => $mouvement->commentaire ?? '',
                    'heure' => $mouvement->created_at->format('H:i:s'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Produit',
            'Type',
            'Quantité',
            'Prix Unitaire',
            'Prix Total',
            'Utilisateur',
            'Commentaire',
            'Heure',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '366092']], 'font' => ['color' => ['rgb' => 'FFFFFF']]],
        ];
    }
}
