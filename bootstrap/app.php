<?php

declare(strict_types=1);

use App\Http\Middleware\CaptureCampaign;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AccessDeniedLogger;
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
        // Payment providers call these without a session; they are verified by signature instead.
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        $middleware->web(append: [CaptureCampaign::class]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // SEC-02: record refused access attempts by signed-in users; the normal 403 page still renders.
        $exceptions->render(function (Throwable $exception, Request $request) {
            AccessDeniedLogger::handle($exception, $request);

            return null;
        });
    })->create();
