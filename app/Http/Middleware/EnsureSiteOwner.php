<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSiteOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth('owner')->check()) {
            return redirect('/owner/login');
        }
        return $next($request);
    }
}
