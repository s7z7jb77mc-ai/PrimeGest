<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'entreprise_id', 'plan', 'amount',
        'payment_method', 'payment_reference',
        'status', 'starts_at', 'expires_at', 'confirmed_by',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
