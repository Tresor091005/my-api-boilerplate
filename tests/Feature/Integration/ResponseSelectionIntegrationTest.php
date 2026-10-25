<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\ResolveResponseSelection;

it('builds the declarations of every application route without error', function (): void {
    $built = app(ResponseDeclarationRegistry::class)->build();

    expect($built)->toHaveKey('Lahatre\\Customer\\Http\\Controllers\\CustomerController@index')
        ->and(count($built))->toBeGreaterThan(70);
});

it('runs in the api group, after authentication when the route requires it', function (): void {
    expect(app('router')->getMiddlewareGroups()['api'])->toContain(ResolveResponseSelection::class);

    $route = app('router')->get('/selection-order-test', fn () => null)->middleware(['api', 'auth.api']);
    $middleware = array_map(fn (string $name) => explode(':', $name)[0], app('router')->gatherRouteMiddleware($route));

    expect(array_search(Authenticate::class, $middleware, true))
        ->toBeLessThan(array_search(ResolveResponseSelection::class, $middleware, true));
});

it('documents the selection parameters of the routes answering through ApiResponse only', function (): void {
    $config = Scramble::getGeneratorConfig('default')
        ->cloneWithoutExposing('response-selection-test')
        ->routes(fn (Route $route): bool => in_array($route->getName(), ['lahatre.catalog.products.index', 'lahatre.inventory.stocks.summary.index'], true));

    $document = app(Generator::class)($config);
    $parameters = fn (string $path): array => array_column($document['paths'][$path]['get']['parameters'] ?? [], 'name');

    expect($parameters('/catalog/products'))->toContain('response', 'fields', 'include')
        ->and($document['paths'])->toHaveKey('/inventory/stocks/summary')
        ->and($parameters('/inventory/stocks/summary'))->not->toContain('fields')
        ->and($parameters('/inventory/stocks/summary'))->not->toContain('include');
});
