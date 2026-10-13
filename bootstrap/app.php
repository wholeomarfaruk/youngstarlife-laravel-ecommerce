<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // New Ops admin panel (Design system setup form) — lives beside the current /admin
            Route::middleware(['web', 'auth', \App\Http\Middleware\OpsAccess::class])
                ->prefix('ops')->name('ops.')->group(base_path('routes/ops.php'));
            Route::middleware('api')->group(base_path('routes/ops-webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // a password change / "Log out other devices" (Ops → My profile) ends every other session of that account
        $middleware->authenticateSessions();
        //  $middleware->append(\App\Http\Middleware\TrackVisits::class);

        // remember ad / UTM parameters for order attribution
        $middleware->web(append: [\App\Http\Middleware\CaptureAttribution::class]);
        // storefront: every visitor gets a device (ys_device cookie) on the first visit — cart and orders hang off it
        $middleware->web(append: [\App\Http\Middleware\RegisterDevice::class]);
        // Ops → Storefront → Store status: maintenance mode closes the public site (staff and panels stay open)
        $middleware->web(append: [\App\Http\Middleware\StorefrontStatus::class]);
        // Meta pixel browser cookies are written by JS (not encrypted); let Laravel read them as-is
        $middleware->encryptCookies(except: ['_fbp', '_fbc']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
