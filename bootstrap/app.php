<?php

declare(strict_types=1);

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveResponseContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;
use Lahatre\Iam\Http\Middleware\SetTeamPermissionsId;
use Lahatre\Shared\Exceptions\AssertionException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            ForceJsonResponse::class,
            ResolveResponseContext::class,
            'throttle:api',
        ]);

        $middleware->group('auth.api', [
            'auth:sanctum',
            ResolveAuthContext::class,
            SetTeamPermissionsId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AssertionException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => [
                        'type'    => class_basename($e),
                        'context' => app()->isProduction() ? null : $e->context(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('shared::validation.failed'),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 403);
            }
        });
    })->create();
