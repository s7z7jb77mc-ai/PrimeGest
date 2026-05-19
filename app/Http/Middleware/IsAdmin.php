<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Accès interdit');
        }

        return $next($request);
    }
}
// This middleware checks if the user is authenticated and has an 'admin' role.
