<?php

use App\Http\Middleware\TeamsPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: ['throttle:120,1'],
            append: [TeamsPermission::class],
        );
        $middleware->api(prepend: ['throttle:60,1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
