<?php

namespace App\Models;

use App\Models\Concerns\HasSuccursaleScope;
use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    use HasFactory, HasUuid, SyncObservable;
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
