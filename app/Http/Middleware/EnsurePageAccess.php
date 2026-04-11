<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageAccess
{
    private array $map = [
        'dashboard' => ['/dashboard'],
        'mouvement_stocks' => ['/mouvement-stocks'],
        'produits' => ['/produits'],
        'clients' => ['/clients'],
        'fournisseurs' => ['/fournisseurs'],
        'journal' => ['/journals'],
        'factures' => ['/factures'],
        'rapports' => ['/rapport'],
        'archives' => ['/archives'],
        'caisse' => ['/caisse'],
        'creances_dettes' => ['/creances-dettes'],
        'ressources_humaines' => ['/ressources-humaines'],
        'succursales' => ['/succursales'],
        'transferts' => ['/transferts'],
        'users' => ['/users'],
        'parametres' => ['/parametres'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');
        if (str_starts_with($path, '/profil') || $path === '/logout' || $path === '/dashboard') {
            return $next($request);
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        $allowed = $user->access_pages ?: [];
        if (empty($allowed)) {
            abort(403, 'Accès non autorisé pour cette page.');
        }

        foreach ($allowed as $key) {
            $prefixes = $this->map[$key] ?? [];
            foreach ($prefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Accès non autorisé pour cette page.');
    }
}
