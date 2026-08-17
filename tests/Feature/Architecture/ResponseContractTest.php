<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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

it('ensures resource include aliases are declared by response contracts', function (): void {
    $registry = app(ResponseContractRegistry::class);
    $declaredIncludes = [];

    foreach ($registry->routeNames() as $routeName) {
        $contract = $registry->forRoute($routeName);

        if ($contract === null) {
            continue;
        }

        foreach ($contract->shapes as $shape) {
            $declaredIncludes = array_merge($declaredIncludes, array_keys($shape->includes));
        }
    }

    $declaredIncludes = array_unique($declaredIncludes);
    $failures = [];

    foreach (File::allFiles(base_path('app-modules')) as $resourceFile) {
        if (!str_ends_with($resourceFile->getFilename(), 'Resource.php')) {
            continue;
        }

        preg_match_all(
            "/include:\s*(?:'([^']+)'|\[([^\]]*)\])/",
            $resourceFile->getContents(),
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $includes = ($match[1] ?? '') !== ''
                ? [$match[1]]
                : (preg_match_all("/'([^']+)'/", $match[2] ?? '', $arrayMatches)
                    ? $arrayMatches[1]
                    : []);

            foreach ($includes as $include) {
                if (!in_array($include, $declaredIncludes, true)) {
                    $failures[] = sprintf(
                        '%s declares include [%s], but no response contract declares that include.',
                        $resourceFile->getRelativePathname(),
                        $include,
                    );
                }
            }
        }
    }

    expect($failures)->toBe([]);
});
