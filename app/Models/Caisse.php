<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Caisse extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $table = 'caisses';

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'date_operation',
        'description',
        'entree',
        'sortie',
        'solde',
        'type_operation',
    ];

    protected $casts = [
        'date_operation' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
