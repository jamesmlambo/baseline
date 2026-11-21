<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * SEC-02 acceptance: when a signed-in user is refused (403), record who tried what, so a
 * housekeeping account opening a payment screen shows up in the audit log. No request body or
 * query string is stored.
 */
final class AccessDeniedLogger
{
    public static function handle(Throwable $exception, Request $request): void
    {
        $denied = $exception instanceof AuthorizationException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);

        if (! $denied || $request->user() === null) {
            return;
        }

        try {
            activity('security')
                ->causedBy($request->user())
                ->event('access_denied')
                ->withProperties([
                    'method' => $request->method(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'route' => $request->route()?->getName(),
                    'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                ])
                ->log('Access denied');
        } catch (Throwable) {
            // Never let logging turn a 403 into a 500.
        }
    }
}
