<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSuccursaleScope;

class ReportLog extends Model
{
    use HasFactory;
    use HasSuccursaleScope;

    protected $fillable = [
        'entreprise_id',
        'succursale_id',
        'user_id',
        'action',
        'report_type',
        'report_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
