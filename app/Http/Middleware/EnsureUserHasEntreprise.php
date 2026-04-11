<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureUserHasEntreprise
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->entreprise_id) {
            return Inertia::render('Entreprise/Create', [
                'message' => 'Veuillez créer votre entreprise pour continuer.',
            ]);
        }

        return $next($request);
    }
}
