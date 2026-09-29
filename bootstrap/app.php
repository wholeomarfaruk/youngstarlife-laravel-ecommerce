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
        //  $middleware->append(\App\Http\Middleware\TrackVisits::class);

        // remember ad / UTM parameters for order attribution
        $middleware->web(append: [\App\Http\Middleware\CaptureAttribution::class]);
        // Meta pixel browser cookies are written by JS (not encrypted); let Laravel read them as-is
        $middleware->encryptCookies(except: ['_fbp', '_fbc']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
