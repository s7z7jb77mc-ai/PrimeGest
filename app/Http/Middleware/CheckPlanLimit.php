<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user       = $request->user();
        $entreprise = $user?->entreprise;

        // Compatibilité avec les bases plus anciennes: si l'infrastructure
        // des plans n'est pas encore migrée, on ne doit pas bloquer les écritures.
        if (!Schema::hasColumn('entreprises', 'plan')) {
            return $next($request);
        }

        if (!$entreprise) {
            return $this->upgradeResponse($request, 'entreprise_not_found', $feature, 'free');
        }

        $plan = $entreprise->plan ?? 'free';

        // Plan expiré → retomber en free côté middleware aussi
        if ($plan !== 'free' && $entreprise->plan_expires_at && Carbon::parse($entreprise->plan_expires_at)->isPast()) {
            $plan = 'free';
        }

        $limits = config("plans.{$plan}");
        $limit  = $limits[$feature] ?? false;

        // Feature booléenne
        if (is_bool($limit)) {
            if (!$limit) {
                return $this->upgradeResponse($request, 'upgrade_required', $feature, $plan);
            }
            return $next($request);
        }

        // Feature numérique
        if (is_int($limit)) {
            if ($limit === -1) return $next($request);

            $count = $this->getCount($entreprise, $feature);
            if ($count >= $limit) {
                return $this->upgradeResponse($request, 'limit_reached', $feature, $plan, $limit, $count);
            }
        }

        return $next($request);
    }

    private function upgradeResponse(
        Request $request,
        string $error,
        string $feature,
        string $plan,
        ?int $limit = null,
        ?int $current = null
    ): Response {
        // Requête Inertia → page Upgrade
        if ($request->header('X-Inertia')) {
            return Inertia::render('Upgrade', [
                'error'   => $error,
                'feature' => $feature,
                'plan'    => $plan,
                'limit'   => $limit,
                'current' => $current,
            ])->toResponse($request);
        }

        // Requête JSON classique
        return response()->json(compact('error', 'feature', 'plan', 'limit', 'current'), 403);
    }

    private function getCount($entreprise, string $feature): int
    {
        return match($feature) {
            'users'        => $entreprise->users()->count(),
            'produits'     => $entreprise->produits()->count(),
            'clients'      => $entreprise->clients()->count(),
            'fournisseurs' => $entreprise->fournisseurs()->count(),
            default        => 0,
        };
    }
}
