<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SyncObservable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Illuminate\Database\Eloquent\SoftDeletes;

class Entreprise extends Model
{
    use HasFactory, HasUuid, SyncObservable;

    // use SoftDeletes;

    protected $fillable = [
        'name', 'uuid', 'slug', 'email', 'phone', 'address',
        'user_id', 'plan', 'plan_expires_at', 'storage_used_mb',
        'sync_version',
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'sync_version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(Setting::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    public function produits()
    {
        return $this->hasMany(\App\Models\Produit::class);
    }

    public function clients()
    {
        return $this->hasMany(\App\Models\Client::class);
    }

    public function fournisseurs()
    {
        return $this->hasMany(\App\Models\Fournisseur::class);
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }
}
