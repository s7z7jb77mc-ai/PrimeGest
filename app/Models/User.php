<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $appends = ['is_super_admin'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'manager',
        'entreprise_id',
        'employe_id',
        'access_pages',
    ];

    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'access_pages' => 'array',
        'manager' => 'boolean',
    ];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function isSuperAdmin(): bool
    {
        $role = strtolower(trim((string) $this->role));
        $role = str_replace([' ', '-'], '_', $role);
        return in_array($role, ['super_admin', 'super_aadmin'], true);
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPassword($token));
    }
}
