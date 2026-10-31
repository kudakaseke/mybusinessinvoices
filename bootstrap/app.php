<?php

use App\Http\Middleware\EnforceRequestSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(EnforceRequestSecurity::class);
        $middleware->web(append: [AuthenticateSession::class, 'throttle:application']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Exception $exception, Request $request): ?Response {
            $unavailableAsset = $request->is('flux/editor.*', 'flux/phone*.js')
                && str_starts_with($exception->getMessage(), 'Flux Pro is required');
            if ($unavailableAsset) {
                return response('Not Found', 404);
            }

            return null;
        });
    })->create();
