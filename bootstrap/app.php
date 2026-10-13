<?php

<<<<<<< HEAD
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
=======
>>>>>>> 51750a58a1f09d53e4c85dbc82f127ae127f86a3
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
<<<<<<< HEAD
        // Sanctum: mark API requests as stateless (token-based)
        $middleware->statefulApi();

        // Register custom RBAC middleware aliases
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
=======
        // TSK-S1-05: Daftarkan middleware RBAC untuk alias penggunaan di routes
        $middleware->alias([
            'role'       => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
>>>>>>> 51750a58a1f09d53e4c85dbc82f127ae127f86a3
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
