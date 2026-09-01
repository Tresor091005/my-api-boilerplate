<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('classifies every route with multiple implicit bindings', function (): void {
    $expectedScopedRoutes = [
        'lahatre.catalog.products.variants.show'      => 'variant',
        'lahatre.catalog.products.variants.update'    => 'variant',
        'lahatre.catalog.products.variants.destroy'   => 'variant',
        'lahatre.catalog.options.values.show'         => 'value',
        'lahatre.catalog.options.values.update'       => 'value',
        'lahatre.catalog.options.values.destroy'      => 'value',
        'lahatre.catalog.bundles.items.update'        => 'item',
        'lahatre.customer.customers.addresses.update' => 'address',
        'lahatre.customer.customers.contacts.update'  => 'contact',
    ];

    /**
     * These parameters are independently resolved and their relationship is
     * verified by InventoryQueryService instead of an Eloquent child binding.
     */
    $expectedIndependentRoutes = [
        'lahatre.inventory.items.locations.lots.index',
    ];

    $multiBindingRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => count($route->parameterNames()) > 1)
        ->keyBy(fn ($route): string => (string) $route->getName());

    expect($multiBindingRoutes->keys()->all())
        ->toEqualCanonicalizing([
            ...array_keys($expectedScopedRoutes),
            ...$expectedIndependentRoutes,
        ]);

    foreach ($expectedScopedRoutes as $routeName => $childParameter) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route '{$routeName}' should exist.");
        expect($route->parameterNames())->toContain($childParameter);
        expect(
            $route->enforcesScopedBindings() || array_key_exists($childParameter, $route->bindingFields())
        )->toBeTrue("Route '{$routeName}' must enforce scoped bindings for '{$childParameter}'.");
    }

    foreach ($expectedIndependentRoutes as $routeName) {
        expect($multiBindingRoutes)->toHaveKey($routeName);
    }
});
