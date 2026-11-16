<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;

it('pins the table of read contexts', function (): void {
    expect(config('read-contexts'))->toBe([
        'customer_customer.create' => [
            'lahatre.catalog.price-levels.index' => ['view' => 'reference', 'replaces' => 'catalog_price_level.list'],
            'lahatre.catalog.price-levels.show'  => ['view' => 'reference', 'replaces' => 'catalog_price_level.retrieve'],
        ],
        'customer_customer.update' => 'customer_customer.create',
    ]);
});

it('names every context after a write permission', function (): void {
    foreach (array_keys(config('read-contexts')) as $context) {
        expect($context)->toMatch('/^[a-z_]+\.[a-z_]+$/');
    }
});

it('opens only existing GET routes whose Resource declares the view', function (): void {
    foreach (config('read-contexts') as $context => $lines) {
        if (is_string($lines)) {
            continue;
        }

        foreach ($lines as $routeName => $line) {
            $route = Route::getRoutes()->getByName($routeName);

            expect($route)->not->toBeNull("{$context}: route {$routeName} must exist.");
            expect($route->methods())->toContain('GET');

            $resourceClass = app(ResponseDeclarationRegistry::class)->for($route)?->resourceClass;

            expect($resourceClass)->not->toBeNull("{$context}: {$routeName} must render a Resource through ApiResponse.");
            expect($resourceClass::definition()->hasView($line['view']))
                ->toBeTrue("{$context}: {$resourceClass} must declare the view {$line['view']}.");
            expect($line['replaces'])->toMatch('/\.(list|retrieve)$/');
        }
    }
});

it('points every alias to a context that exists and is not an alias', function (): void {
    $table = config('read-contexts');

    foreach ($table as $context => $lines) {
        if (!is_string($lines)) {
            continue;
        }

        expect($table)->toHaveKey($lines);
        expect($table[$lines])->toBeArray("{$context} must not point to another alias.");
    }
});
