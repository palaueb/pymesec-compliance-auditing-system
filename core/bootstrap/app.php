<?php

use App\Http\Middleware\AuditHttpOperation;
use App\Http\Middleware\AuthorizePermission;
use App\Http\Middleware\CanonicalizeAuthenticatedPrincipal;
use App\Http\Middleware\ResolveApiPrincipal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CanonicalizeAuthenticatedPrincipal::class,
            AuditHttpOperation::class,
        ]);

        $middleware->api(append: [
            ResolveApiPrincipal::class,
            AuditHttpOperation::class,
        ]);

        $middleware->alias([
            'core.permission' => AuthorizePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'Validation failed.',
                    'details' => $exception->errors(),
                    'request_id' => $request->attributes->get('core.request_id'),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'authentication_failed',
                    'message' => 'Authentication required.',
                    'request_id' => $request->attributes->get('core.request_id'),
                ],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'authorization_failed',
                    'message' => 'Forbidden.',
                    'request_id' => $request->attributes->get('core.request_id'),
                ],
            ], 403);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return response()->json([
                'error' => [
                    'code' => $status >= 500 ? 'internal_error' : 'request_failed',
                    'message' => $status >= 500 ? 'Internal server error.' : $exception->getMessage(),
                    'request_id' => $request->attributes->get('core.request_id'),
                ],
            ], $status);
        });
    })->create();
