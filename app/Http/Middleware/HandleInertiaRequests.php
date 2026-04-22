<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Succursale;
use Carbon\Carbon;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $parametres     = null;
        $hasSuccursales = false;
        $succursaleId   = null;
        $succursaleName = null;
        $plan           = 'free';
        $planLimits     = config('plans.free');
        $planExpiresAt  = null;
        $entreprise     = null;

        $user = $request->user();

        if ($user) {
            $parametres     = \App\Models\Parametre::where('entreprise_id', $user->entreprise_id)->first();
            $hasSuccursales = Succursale::where('entreprise_id', $user->entreprise_id)->exists();
            $succursaleId   = session('succursale_id');

            if ($succursaleId) {
                $succursaleName = Succursale::where('entreprise_id', $user->entreprise_id)
                    ->where('id', $succursaleId)
                    ->value('nom');
            }

            // Plan — fresh depuis DB
            $entreprise    = $user->entreprise()->first();
            $plan          = $entreprise?->plan ?? 'free';
            $planExpiresAt = $entreprise?->plan_expires_at;

            // Plan expiré → retomber en free
            if ($plan !== 'free' && $planExpiresAt && Carbon::parse($planExpiresAt)->isPast()) {
                $plan = 'free';
            }

            $planLimits = config('plans.' . $plan) ?? config('plans.free');
        }

        $canManage = $user?->isSuperAdmin() === true;

        return [
            ...parent::share($request),
            'auth'            => ['user' => $user],
            'plan'            => $plan,
            'plan_limits'     => $planLimits,
            'plan_expires_at' => $planExpiresAt,
            'can_manage'      => $canManage,
            'parametres'      => $parametres,
            'has_succursales' => $hasSuccursales,
            'succursale_id'   => $succursaleId,
            'succursale_name' => $succursaleName,
        ];
    }
}
