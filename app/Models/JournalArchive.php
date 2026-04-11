<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalArchive extends Model
{
    use HasFactory;

    protected $table = 'journal_archives';

    protected $fillable = [
        'entreprise_id',
        'produit_id',
        'user_id',
        'dateHeure_operation',
        'type',
        'description',
        'montant',
        'date_archive',
    ];

    protected $casts = [
        'dateHeure_operation' => 'datetime',
        'date_archive' => 'date',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
