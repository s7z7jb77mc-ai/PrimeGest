<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonEntree extends Model
{
    use HasFactory, HasUuid, SyncObservable;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'fournisseur_id',
        'numero',
        'total_montant',
        'date_bon',
        'payment_type',
    ];

    protected $casts = [
        'date_bon' => 'datetime',
    ];

    public function lignes()
    {
        return $this->hasMany(BonEntreeLigne::class, 'bon_entree_id');
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bon) {
            if (empty($bon->numero)) {
                $last = self::latest('id')->first();
                $nextNumber = $last ? $last->id + 1 : 1;
                $bon->numero = 'BE-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
