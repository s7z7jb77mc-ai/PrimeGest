<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MouvementStockArchive extends Model
{
    use HasFactory, HasUuid, SyncObservable;

    protected $table = 'mouvement_stock_archives';

    protected $fillable = [
        'entreprise_id',
        'produit_id',
        'type',
        'quantite',
        'prix_unitaire',
        'prix_total',
        'user_id',
        'commentaire',
        'date_archive',
        'mouvement_stock_id',
    ];

    protected $casts = [
        'date_archive' => 'date',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
