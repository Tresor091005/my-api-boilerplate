<?php

declare(strict_types=1);

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveResponseContext;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Lahatre\Iam\Http\Middleware\EnsureProfileComplete;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;
use Lahatre\Iam\Http\Middleware\SetTeamPermissionsId;
use Lahatre\Iam\Http\Middleware\TrackSessionActivity;
use Lahatre\Shared\Exceptions\AssertionException;
use Lahatre\Shared\Http\Response\ResolveResponseSelection;
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
        // Proxies come from config/trustedproxy.php. Only the client IP and
        // scheme are taken from them: host, port and prefix stay untrusted so
        // absolute URLs keep the API's own host, not the SPA origin in front.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->appendToGroup('api', [
            ForceJsonResponse::class,
            ResolveResponseContext::class,
            ResolveResponseSelection::class,
            'throttle:api',
        ]);

        $middleware->group('auth.api', [
            'auth:sanctum',
            EnsureProfileComplete::class,
            TrackSessionActivity::class,
            ResolveAuthContext::class,
            SetTeamPermissionsId::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveAuthContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTeamPermissionsId::class);
        $middleware->prependToPriorityList(ResolveAuthContext::class, TrackSessionActivity::class);
        $middleware->prependToPriorityList(TrackSessionActivity::class, EnsureProfileComplete::class);
        // Authentication answers first: a logged-out request gets 401, not a 422 for its selection.
        $middleware->appendToPriorityList(AuthenticatesRequests::class, ResolveResponseSelection::class);
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
