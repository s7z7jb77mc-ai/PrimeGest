<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $entreprise = $user->entreprise;

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

            Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'expired']);
        }

        return $next($request);
    }
}
