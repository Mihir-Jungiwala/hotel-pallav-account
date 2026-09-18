<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'account.usable' => \App\Http\Middleware\EnsureAccountIsUsable::class,
            'role.permissions' => \App\Http\Middleware\EnforceRolePermissions::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'superadmin' => \App\Http\Middleware\RequireSuperAdmin::class,
            'single.session' => \App\Http\Middleware\EnsureSingleSession::class,
        ]);

        // Signed-in users are sent to the dashboard, guests to the login page
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
