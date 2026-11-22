<?php

use App\Exceptions\Handler;
use App\Http\Middleware\RequireAccess;
use App\Http\Middleware\RequireActiveSession;
use App\Http\Middleware\RequireConsents;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        $middleware->authenticateSessions();
        $middleware->web(append: RequireActiveSession::class);
        $middleware->alias(['access' => RequireAccess::class, 'consents' => RequireConsents::class]);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homePath() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'form_token', 'data.password']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSingletons([ExceptionHandler::class => Handler::class])
    ->create();
