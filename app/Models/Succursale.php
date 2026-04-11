<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Succursale extends Model
{
    protected $fillable = [
        'entreprise_id',
        'nom',
        'adresse',
        'manager_user_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
