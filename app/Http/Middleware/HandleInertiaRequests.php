<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Succursale;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $parametres = null;
        $hasSuccursales = false;
        $succursaleId = null;
        $succursaleName = null;
        $user = $request->user();
        if ($user) {
            $parametres = \App\Models\Parametre::where('entreprise_id', $user->entreprise_id)->first();
            $hasSuccursales = Succursale::where('entreprise_id', $user->entreprise_id)->exists();
            $succursaleId = session('succursale_id');
            if ($succursaleId) {
                $succursaleName = Succursale::where('entreprise_id', $user->entreprise_id)
                    ->where('id', $succursaleId)
                    ->value('nom');
            }
        }
        $canManage = $user?->isSuperAdmin() === true;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'can_manage' => $canManage,
            'parametres' => $parametres,
            'has_succursales' => $hasSuccursales,
            'succursale_id' => $succursaleId,
            'succursale_name' => $succursaleName,
        ];
    }
}
