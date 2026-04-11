<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    protected $fillable = [
        'name', 'uuid', 'slug', 'email', 'phone', 'address', 'user_id'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function settings()
    {
        return $this->hasOne(Setting::class);
    }

}
