<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    protected $fillable = [
        'entreprise_id',
        'nom_entreprise',
        'adresse',
        'email',
        'telephone',
        'devise',
        'langue',
        'rccm',
        'identifiant_national',
        'numero_impot',
        'tva',
        'reduction_accordee',
        'theme',
        'message_remerciement',
        'multi_succursales',
        'seuil_alerte',
        'logo_path',
        'logo',
        'logo_position',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute()
    {
        $path = $this->logo_path ?: ($this->attributes['logo'] ?? null);
        if (!$path) {
            return null;
        }
        $normalized = str_replace('\\', '/', trim((string) $path));
        $normalized = ltrim($normalized, '/');

        if (str_contains($normalized, 'logos/')) {
            $normalized = substr($normalized, strpos($normalized, 'logos/'));
        }

        if (!str_contains($normalized, '/') && preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $normalized)) {
            $normalized = 'logos/' . $normalized;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        if (str_starts_with($normalized, 'logos/')) {
            $publicFile = public_path($normalized);
            if (is_file($publicFile)) {
                return asset($normalized);
            }
        }

        return asset('storage/' . $normalized);
    }
}
