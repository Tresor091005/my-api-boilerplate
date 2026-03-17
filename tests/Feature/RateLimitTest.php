<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('has an api rate limiter configured for 90 requests per minute', function (): void {
    $limiter = RateLimiter::limiter('api');

    expect($limiter)->toBeCallable();

    $request = Request::create('/v1/catalog/products', 'GET');
    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class);
    expect($limit->maxAttempts)->toBe(90);
    expect($limit->decaySeconds)->toBe(60);
});

it('has an auth rate limiter configured for 5 requests per minute', function (): void {
    $limiter = RateLimiter::limiter('auth');

    expect($limiter)->toBeCallable();

    $request = Request::create('/v1/auth/login', 'POST');
    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class);
    expect($limit->maxAttempts)->toBe(5);
    expect($limit->decaySeconds)->toBe(60);
});

it('ensures all api routes are throttled correctly', function (): void {
    $apiRoutes = collect(Route::getRoutes())->filter(function ($route): bool {
        $uri = $route->uri();

        return str_starts_with($uri, 'v1/') || str_starts_with($uri, 'api/');
    });

    $router = app('router');

    foreach ($apiRoutes as $route) {
        // Resolve all middleware (expand groups)
        $middleware = $router->resolveMiddleware($route->gatherMiddleware());
        $uri = $route->uri();

        // Check if any throttle middleware is applied
        $hasThrottle = collect($middleware)->contains(fn ($m): bool => is_string($m) && (
            str_starts_with($m, 'throttle:') ||
            str_starts_with($m, ThrottleRequests::class)
        ));

        expect($hasThrottle)->toBeTrue("Route [{$uri}] is not throttled.");

        // Specific checks for login/register
        if (str_contains((string) $uri, 'login') || str_contains((string) $uri, 'register')) {
            $hasAuthThrottle = collect($middleware)->contains(fn ($m): bool => is_string($m) && (
                $m === 'throttle:auth' ||
                $m === ThrottleRequests::class.':auth'
            ));
            expect($hasAuthThrottle)->toBeTrue("Route [{$uri}] should use 'throttle:auth'.");
        } else {
            // All other API routes should use 'throttle:api' (via group)
            $hasApiThrottle = collect($middleware)->contains(fn ($m): bool => is_string($m) && (
                $m === 'throttle:api' ||
                $m === ThrottleRequests::class.':api'
            ));
            expect($hasApiThrottle)->toBeTrue("Route [{$uri}] should use 'throttle:api'.");
        }
    }
});

it('uses the dedicated limiter cache store', function (): void {
    /** @var Illuminate\Cache\RateLimiter $rateLimiter */
    $rateLimiter = app(Illuminate\Cache\RateLimiter::class);

    // Using reflection to check the cache driver of the rate limiter
    $reflection = new ReflectionClass($rateLimiter);
    $cacheProperty = $reflection->getProperty('cache');
    $cache = $cacheProperty->getValue($rateLimiter);

    // If using a repository, get the actual store
    if ($cache instanceof Repository) {
        $cache = $cache->getStore();
    }

    if ($cache instanceof RedisStore) {
        $reflectionStore = new ReflectionClass($cache);
        $connectionProperty = $reflectionStore->getProperty('connection');
        $connection = $connectionProperty->getValue($cache);

        expect(config('cache.limiter'))->toBe('redis-limiter');
        expect($connection)->toBe('limiter');
    } else {
        // Fallback or skip if not using Redis in this environment
        expect(config('cache.limiter'))->toBeIn(['array', 'database']);
    }

    // TODO: enforce project to work under redis ? and for testing too ?
});
