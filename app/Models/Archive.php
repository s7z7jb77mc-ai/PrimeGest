<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class Archive extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $table = 'archives';

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'type',
        'date_archive',
        'reference_id',
        'payload',
    ];

    protected $casts = [
        'date_archive' => 'date',
        'payload' => 'array',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
