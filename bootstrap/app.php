<?php

use App\Modules\Identity\Console\Commands\CreateSystemAdminCommand;
use App\Modules\Identity\Http\Middleware\EnsureSystemAdmin;
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
    ->withCommands([
        CreateSystemAdminCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepends EnsureFrontendRequestsAreStateful to the api group so
        // requests from the configured stateful domains authenticate with the
        // session cookie and CSRF token instead of a bearer token (AUTH-001).
        $middleware->statefulApi();

        $middleware->alias([
            'system_admin' => EnsureSystemAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
