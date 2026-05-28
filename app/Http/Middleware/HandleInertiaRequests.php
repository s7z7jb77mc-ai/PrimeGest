<?php

namespace App\Http\Middleware;

use App\Models\Succursale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $parametres      = null;
        $hasSuccursales  = false;
        $succursaleId    = null;
        $succursaleName  = null;
        $plan            = 'free';
        $planLimits      = config('plans.free');
        $planExpiresAt   = null;

        $user = $request->user();

        if ($user) {
            $eid = $user->entreprise_id;

            // ── Paramètres : cache 5 min par entreprise ──────────────────
            $parametres = Cache::remember("inertia.parametres.{$eid}", 300, function () use ($eid) {
                try {
                    return \App\Models\Parametre::where('entreprise_id', $eid)->first();
                } catch (\Throwable) {
                    return null;
                }
            });

            // ── Plan entreprise : résoudre en premier pour les checks suivants ─
            $entreprise = $user->relationLoaded('entreprise')
                ? $user->entreprise
                : Cache::remember("inertia.entreprise.{$eid}", 60, fn () => $user->entreprise()->first());

            $plan          = $entreprise?->plan ?? 'free';
            $planExpiresAt = $entreprise?->plan_expires_at;

            if ($plan !== 'free' && $planExpiresAt && Carbon::parse($planExpiresAt)->isPast()) {
                $plan = 'free';
            }

            $planLimits = config('plans.'.$plan) ?? config('plans.free');

            // ── Succursales : cache 2 min par entreprise ──────────────────
            $hasSuccursales = Cache::remember("inertia.has_succursales.{$eid}", 120, function () use ($eid) {
                try {
                    return Succursale::where('entreprise_id', $eid)->exists();
                } catch (\Throwable) {
                    return false;
                }
            });

            $succursaleId = session('succursale_id');

            // Si le plan ne permet plus les succursales (rétrogradation Pro→Premium/Free),
            // vider le contexte de session lors des navigations (GET) pour éviter qu'un
            // utilisateur reste bloqué dans une succursale à laquelle il n'a plus accès.
            // On ne touche pas à la session sur les POST/PUT/DELETE : le middleware plan:succursales
            // bloque déjà les routes d'écriture, et les autres contrôleurs ne doivent pas
            // changer de comportement en cours de requête à cause d'un effacement imprévu.
            if ($succursaleId && !($planLimits['succursales'] ?? false) && $request->isMethod('GET')) {
                session()->forget('succursale_id');
                $succursaleId = null;
            }

            if ($succursaleId) {
                $succursaleName = Cache::remember(
                    "inertia.succursale_name.{$eid}.{$succursaleId}",
                    120,
                    fn () => Succursale::where('entreprise_id', $eid)
                        ->where('id', $succursaleId)
                        ->value('nom')
                );
            }
        }

        return [
            ...parent::share($request),
            'auth'            => ['user' => $user],
            'plan'            => $plan,
            'plan_limits'     => $planLimits,
            'plan_expires_at' => $planExpiresAt,
            'can_manage'      => $user?->isSuperAdmin() === true,
            'parametres'      => $parametres,
            'has_succursales' => $hasSuccursales,
            'succursale_id'   => $succursaleId,
            'succursale_name' => $succursaleName,
        ];
    }
}
