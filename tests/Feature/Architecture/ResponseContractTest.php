<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Shared\Registries\ResponseContractRegistry;

it('ensures every application api route has a response contract', function (): void {
    $registry = app(ResponseContractRegistry::class);
    $failures = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (!in_array('api', $route->gatherMiddleware(), true)) {
            continue;
        }

        $routeName = $route->getName();

        if ($routeName === null || $registry->forRoute($routeName) === null) {
            $failures[] = sprintf(
                'Route [%s] (%s) is missing a response contract. Add it to the response-contracts.php file owned by its module.',
                $routeName ?? $route->uri(),
                implode('|', $route->methods()),
            );
        }
    }

    if ($failures !== []) {
        $this->fail("Response Contract Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures response contracts point to existing application api routes', function (): void {
    $apiRouteNames = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (in_array('api', $route->gatherMiddleware(), true) && $route->getName() !== null) {
            $apiRouteNames[] = $route->getName();
        }
    }

    $staleContracts = array_diff(
        app(ResponseContractRegistry::class)->routeNames(),
        $apiRouteNames,
    );

    expect($staleContracts)->toBe([]);
});
