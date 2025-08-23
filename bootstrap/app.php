<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: base_path('routes/web.php'),
        commands: base_path('routes/console.php'),
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Use our custom role middleware:
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,

            // (Optional) keep these only if you use them in routes:
            // 'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
            // 'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();
