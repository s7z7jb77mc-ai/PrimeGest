<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user       = $request->user();
        $entreprise = $user?->entreprise;

        if (!$entreprise) {
            return response()->json(['error' => 'entreprise_not_found'], 403);
        }

        $plan   = $entreprise->plan ?? 'free';
        $limits = config("plan_limits.{$plan}");
        $limit  = $limits[$feature] ?? false;

        // Feature booléenne (dette_tracking, reductions, succursales, exports)
        if (is_bool($limit)) {
            if (!$limit) {
                return response()->json([
                    'error'   => 'upgrade_required',
                    'feature' => $feature,
                    'plan'    => $plan,
                ], 403);
            }
            return $next($request);
        }

        // Feature numérique (users, produits, clients, fournisseurs)
        if (is_int($limit)) {
            if ($limit === -1) return $next($request);

            $count = $this->getCount($entreprise, $feature);
            if ($count >= $limit) {
                return response()->json([
                    'error'   => 'limit_reached',
                    'feature' => $feature,
                    'limit'   => $limit,
                    'current' => $count,
                ], 403);
            }
        }

        return $next($request);
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
