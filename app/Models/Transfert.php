<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Transfert extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'from_succursale_id',
        'to_succursale_id',
        'produit_id',
        'user_id',
        'approved_by',
        'approved_at',
        'type',
        'montant',
        'quantite',
        'status',
        'date_operation',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'date_operation' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function fromSuccursale()
    {
        return $this->belongsTo(Succursale::class, 'from_succursale_id');
    }

    public function toSuccursale()
    {
        return $this->belongsTo(Succursale::class, 'to_succursale_id');
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
