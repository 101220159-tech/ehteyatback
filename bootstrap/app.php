<?php

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => ['api', 'auth:sanctum'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role_or_permission' => \App\Http\Middleware\CheckRoleOrPermission::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found.',
                    'errors' => [],
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => [],
                ], 401);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            if ($e instanceof ValidationException
                || $e instanceof NotFoundHttpException
                || $e instanceof AuthenticationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = config('app.debug') && $status >= 500
                ? $e->getMessage()
                : match ($status) {
                    403 => 'Forbidden.',
                    503 => 'Service temporarily unavailable.',
                    default => $status >= 500 ? 'Server error. Please try again.' : 'Request failed.',
                };

            return response()->json(['message' => $message, 'errors' => []], $status);
        });
    })->create();
