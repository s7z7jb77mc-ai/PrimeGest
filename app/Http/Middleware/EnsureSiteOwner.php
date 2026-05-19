<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSiteOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth('owner')->check()) {
            if ($request->header('X-Inertia')) {
                return response()->json(['error' => 'unauthenticated'], 401);
            }
            return redirect('/owner/login');
        }
        return $next($request);
    }
}
