<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ✅ Trust Cloudflare proxies
        $middleware->trustProxies(at: '*');

        // ✅ Webhooks exclus du CSRF
        $middleware->validateCsrfTokens(except: [
            'api/v1/payment/webhook',
            'payment/callback',
        ]);

        // ✅ Middlewares web (ordre important)
        // Sanctum SPA : authentification par session pour les routes API (fetch depuis le navigateur)
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureWritableAccess::class,
            \App\Http\Middleware\EnsurePageAccess::class,
            \App\Http\Middleware\EnsurePlanNotExpired::class,
        ]);

        // ✅ Aliases
        $middleware->alias([
            'plan' => \App\Http\Middleware\CheckPlanLimit::class,
            'owner' => \App\Http\Middleware\EnsureSiteOwner::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'hasEntreprise' => \App\Http\Middleware\EnsureUserHasEntreprise::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
