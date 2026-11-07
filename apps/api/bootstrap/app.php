<?php

use App\Http\Middleware\ApiShield;
use App\Http\Middleware\BotGuard;
use App\Http\Middleware\CatalogGuard;
use App\Http\Middleware\CatalogThrottle;
use App\Http\Middleware\EnforceFeatureSwitches;
use App\Http\Middleware\InternalAwareThrottle;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API clients get a 401 JSON instead of a redirect to a login page.
        $middleware->alias(['feature' => RequireFeature::class]);
        // Security module (docs/SECURITY.md): headers everywhere, blocklist + OTP abuse guard on the API,
        // "catalog.guard" (signed clients, bot detection, progressive rate limits) wraps the catalog routes.
        $middleware->append(SecurityHeaders::class);
        $middleware->prependToGroup('api', App\Http\Middleware\PublicApiThrottle::class); // System → Limits: per-IP API ceiling + 429 counter
        $middleware->appendToGroup('api', ApiShield::class);
        // Feature switches: checkout / signups / payment methods / coupons / points / mobile app (Admin → System → Features).
        $middleware->appendToGroup('api', EnforceFeatureSwitches::class);
        // alias() replaces the previous alias list, so "feature" is repeated here.
        $middleware->alias(['feature' => RequireFeature::class, 'throttle' => InternalAwareThrottle::class]);
        $middleware->group('catalog.guard', [
            CatalogGuard::class,
            BotGuard::class,
            CatalogThrottle::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
