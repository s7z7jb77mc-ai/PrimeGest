<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $eid = $user->entreprise_id;

        $entreprise = Cache::remember("inertia.entreprise.{$eid}", 60,
            fn () => $user->entreprise()->first()
        );

        if (! $entreprise) {
            return $next($request);
        }

        if (
            $entreprise->plan !== 'free'
            && $entreprise->plan_expires_at !== null
            && $entreprise->plan_expires_at->isPast()
        ) {
            $entreprise->update([
                'plan' => 'free',
                'plan_expires_at' => null,
            ]);

            Cache::forget("inertia.entreprise.{$eid}");

            Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'expired']);
        }

        // Eager-load so HandleInertiaRequests reuses it without a second query
        $user->setRelation('entreprise', $entreprise);

        return $next($request);
    }
}
