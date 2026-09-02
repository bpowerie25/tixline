<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequirePlan;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\ResolveApiTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Webhooks cannot carry a CSRF token, so their URIs are exempted here.
        //
        // This is deliberately not done with withoutMiddleware() at the route.
        // The web group registers PreventRequestForgery, while the routes
        // excluded Illuminate\Foundation\Http\Middleware\VerifyCsrfToken --
        // a deprecated subclass under a different name, so the exclusion
        // matched nothing and every webhook was answered with a 419. Nothing
        // caught it: the middleware short-circuits on runningUnitTests(), so
        // the feature tests posted to these routes and passed while the real
        // endpoints rejected every delivery.
        $middleware->validateCsrfTokens(except: [
            'inbound/email',
            'inbound/mailgun',
        ]);

        // Ahead of the group's SubstituteBindings, so route model binding is
        // already tenant-scoped. See ResolveApiTenant.
        $middleware->api(prepend: [
            ResolveApiTenant::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ResolveTenant::class,
        ]);

        $middleware->alias([
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
            'plan' => RequirePlan::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal', 'portal/*')) {
                return route('portal.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
