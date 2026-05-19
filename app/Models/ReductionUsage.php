<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class ReductionUsage extends Model
{
    use HasFactory;
    use HasSuccursaleScope;
    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'entity_type',
        'entity_id',
        'montant_utilise',
        'reste_apres',
    ];
}
