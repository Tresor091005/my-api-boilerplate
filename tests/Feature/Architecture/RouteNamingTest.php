<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('ensures all application routes have a name', function (): void {
    $routes = Route::getRoutes();
    $failures = [];

    foreach ($routes->getRoutes() as $route) {
        $uri = $route->uri();

        // Skip internal/vendor routes
        if (shouldIgnoreRoute($route)) {
            continue;
        }

        if ($route->getName() === null || $route->getName() === '') {
            $failures[] = "Route [{$uri}] is missing a name.";
        }
    }

    if ($failures !== []) {
        $this->fail("Route Naming Failures (Missing Names):\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all application route names follow the dot or dash format and never use underscores', function (): void {
    $routes = Route::getRoutes();
    $failures = [];

    foreach ($routes->getRoutes() as $route) {
        $name = $route->getName();

        // Skip internal/vendor routes or routes already caught by the "missing name" test
        if (shouldIgnoreRoute($route) || $name === null || $name === '') {
            continue;
        }

        // Check for underscores
        if (Str::contains($name, '_')) {
            $failures[] = "Route name [{$name}] contains underscores. Use dots (.) or dashes (-) instead.";
        }

        // Check for allowed characters (alphanumeric, dots, dashes)
        if (!preg_match('/^[a-z0-0.-]+$/', (string) $name)) {
            $failures[] = "Route name [{$name}] contains invalid characters. Only lowercase alphanumeric, dots (.) and dashes (-) are allowed.";
        }
    }

    if ($failures !== []) {
        $this->fail("Route Naming Failures (Format):\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

/**
 * Determine if a route should be ignored by architectural tests.
 */
function shouldIgnoreRoute($route): bool
{
    $uri = $route->uri();
    $name = $route->getName();

    return Str::startsWith($uri, '_boost') ||
           Str::startsWith($uri, 'debug') ||
           Str::startsWith($uri, 'queues') ||
           Str::startsWith($uri, 'horizon') ||
           Str::startsWith($uri, 'telescope') ||
           Str::startsWith($uri, 'broadcasting') ||
           $uri === 'up' ||
           $uri === '/' ||
           $name === 'sanctum.csrf-cookie' ||
           $name === 'storage.local' ||
           Str::startsWith((string) $name, 'scramble.');
}
