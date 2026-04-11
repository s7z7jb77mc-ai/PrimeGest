<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWritableAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && strtolower((string) $user->role) === 'user') {
            $method = strtoupper($request->getMethod());
            if (!in_array($method, ['GET', 'HEAD'], true)) {
                if ($request->route()?->getName() !== 'logout') {
                    abort(403, 'Accès en lecture seule.');
                }
            }
        }

        return $next($request);
    }
}
