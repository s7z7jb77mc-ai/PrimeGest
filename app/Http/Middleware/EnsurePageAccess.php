<?php

namespace App\Http\Middleware;

use App\Models\Succursale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageAccess
{
    /**
     * Map des clés access_pages vers les préfixes de routes.
     */
    private array $map = [
        'dashboard' => ['/dashboard'],
        'mouvement_stocks' => ['/mouvement-stocks'],
        'produits' => ['/produits'],
        'clients' => ['/clients', '/tiers'],
        'fournisseurs' => ['/fournisseurs', '/tiers'],
        'tiers' => ['/tiers'],
        'journal' => ['/journals'],
        'factures' => ['/factures'],
        'rapports' => ['/rapport'],
        'archives' => ['/archives'],
        'caisse' => ['/caisse'],
        'creances_dettes' => ['/creances-dettes'],
        'ressources_humaines' => ['/ressources-humaines', '/employes', '/fiches'],
        'succursales' => ['/succursales'],
        'transferts' => ['/transferts'],
        'users' => ['/users'],
        'parametres' => ['/parametres'],
    ];

    /**
     * ✅ Pages auxquelles le manager a toujours accès dans SA succursale,
     * même sans les avoir dans access_pages.
     * Utilise les vrais préfixes de routes.
     */
    private array $managerDefaultPages = [
        '/dashboard',
        '/mouvement-stocks',
        '/produits',
        '/tiers',           // module fusionné clients + fournisseurs
        '/clients',
        '/fournisseurs',
        '/journals',
        '/caisse',
        '/archives',
        '/transferts',
        '/creances-dettes',
        '/ressources-humaines',
        '/employes',        // ✅ vrai chemin des employés
        '/fiches',          // ✅ vrai chemin des fiches de paie
        '/users',
        '/factures',
        '/rapport',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        // Toujours autorisé (pages système et auth)
        if (
            str_starts_with($path, '/profil') ||
            str_starts_with($path, '/profile') ||
            str_starts_with($path, '/password') ||
            str_starts_with($path, '/settings') ||
            str_starts_with($path, '/verify-email') ||
            str_starts_with($path, '/confirm-password') ||
            str_starts_with($path, '/email') ||
            str_starts_with($path, '/abonnement') ||
            $path === '/logout' ||
            $path === '/dashboard' ||
            str_starts_with($path, '/fiche-paye')
        ) {
            return $next($request);
        }

        // Super admin — accès total
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // ✅ Manager de la succursale active — accès complet à sa succursale
        $succursaleId = session('succursale_id');
        \Illuminate\Support\Facades\Log::info('EnsurePageAccess', [
            'user' => $user->id, 'path' => $path,
            'succursale_id' => $succursaleId, 'manager' => $user->manager,
        ]);
        if ($succursaleId) {
            try {
                $succursale = Cache::remember(
                    "inertia.succursale_obj.{$user->entreprise_id}.{$succursaleId}",
                    120,
                    fn () => Succursale::where('id', $succursaleId)
                        ->where('entreprise_id', $user->entreprise_id)
                        ->first()
                );
            } catch (\Throwable) {
                $succursale = null;
            }

            \Illuminate\Support\Facades\Log::info('EnsurePageAccess manager check', [
                'succursale_found' => $succursale ? $succursale->id : null,
                'manager_user_id' => $succursale?->manager_user_id,
                'user_id' => $user->id,
                'match' => $succursale ? ((int)$succursale->manager_user_id === (int)$user->id) : false,
            ]);

            if ($succursale && (int) $succursale->manager_user_id === (int) $user->id) {
                return $next($request);
            }
        }

        // Autres utilisateurs — vérification access_pages
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
