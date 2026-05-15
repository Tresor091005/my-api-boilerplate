<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('enforces scoped bindings for nested catalog resources', function (): void {
    $expectedNestedRoutes = [
        'lahatre.catalog.products.variants.show'    => 'variant',
        'lahatre.catalog.products.variants.update'  => 'variant',
        'lahatre.catalog.products.variants.destroy' => 'variant',
        'lahatre.catalog.options.values.show'       => 'value',
        'lahatre.catalog.options.values.update'     => 'value',
        'lahatre.catalog.options.values.destroy'    => 'value',
    ];

    foreach ($expectedNestedRoutes as $routeName => $childParameter) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route '{$routeName}' should exist.");
        expect(
            $route->enforcesScopedBindings() || array_key_exists($childParameter, $route->bindingFields())
        )->toBeTrue("Route '{$routeName}' must enforce scoped bindings for '{$childParameter}'.");
    }
});
