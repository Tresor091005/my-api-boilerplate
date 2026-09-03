<?php

declare(strict_types=1);

$categoryCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'bloodline' => ['loads' => ['bloodline']],
    ]]],
];

$categoryResource = $categoryCollection;

$optionCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'values' => ['loads' => ['values']],
    ]]],
];

$optionResource = $optionCollection;

$optionValueCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'option' => ['loads' => ['option']],
    ]]],
];

$optionValueResource = $optionValueCollection;

$productCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'categories' => ['loads' => ['categories']],
        'options'    => ['loads' => ['optionValues.option']],
        'variants'   => ['loads' => ['variants.catalogItem', 'variants.product', 'variants.optionValues.option']],
    ]]],
];

$productResource = $productCollection;

$variantCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => [
        'required_loads' => ['product', 'catalogItem', 'optionValues.option'],
        'includes'       => [
            'unit_group' => ['loads' => ['catalogItem.unitGroup']],
            'units'      => ['loads' => ['catalogItem.unitGroup.units']],
            'labels'     => ['loads' => ['labels']],
            'inventory'  => ['loads' => ['catalogItem.inventoryItem.stockSummaries']],
        ],
    ]],
];

$variantResource = $variantCollection;

$bundleCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => [
        'required_loads' => ['catalogItem'],
        'includes'       => [
            'items'      => ['loads' => ['items.component']],
            'unit_group' => ['loads' => ['catalogItem.unitGroup']],
            'units'      => ['loads' => ['catalogItem.unitGroup.units']],
            'inventory'  => ['loads' => ['catalogItem.inventoryItem.stockSummaries']],
        ],
    ]],
];

$bundleResource = $bundleCollection;

$stockLocationCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'address' => ['loads' => ['address']],
    ]]],
];

$stockLocationResource = $stockLocationCollection;

return [
    'lahatre.catalog.categories.index'                    => $categoryCollection,
    'lahatre.catalog.categories.show'                     => $categoryResource,
    'lahatre.catalog.categories.store'                    => $categoryResource,
    'lahatre.catalog.categories.update'                   => $categoryResource,
    'lahatre.catalog.options.index'                       => $optionCollection,
    'lahatre.catalog.options.show'                        => $optionResource,
    'lahatre.catalog.options.store'                       => $optionResource,
    'lahatre.catalog.options.update'                      => $optionResource,
    'lahatre.catalog.options.values.index'                => $optionValueCollection,
    'lahatre.catalog.options.values.store'                => $optionValueCollection,
    'lahatre.catalog.options.values.show'                 => $optionValueResource,
    'lahatre.catalog.options.values.update'               => $optionValueResource,
    'lahatre.catalog.products.index'                      => $productCollection,
    'lahatre.catalog.products.show'                       => $productResource,
    'lahatre.catalog.products.store'                      => $productResource,
    'lahatre.catalog.products.update'                     => $productResource,
    'lahatre.catalog.products.variants.index'             => $variantCollection,
    'lahatre.catalog.products.variants.activation.update' => [],
    'lahatre.catalog.products.variants.store'             => $variantCollection,
    'lahatre.catalog.products.variants.show'              => $variantResource,
    'lahatre.catalog.products.variants.update'            => $variantResource,
    'lahatre.catalog.bundles.index'                       => $bundleCollection,
    'lahatre.catalog.bundles.show'                        => $bundleResource,
    'lahatre.catalog.bundles.store'                       => $bundleResource,
    'lahatre.catalog.bundles.update'                      => $bundleResource,
    'lahatre.catalog.stock-locations.index'               => $stockLocationCollection,
    'lahatre.catalog.stock-locations.show'                => $stockLocationResource,
    'lahatre.catalog.stock-locations.store'               => $stockLocationResource,
    'lahatre.catalog.stock-locations.update'              => $stockLocationResource,
    'lahatre.catalog.bundles.items.store'                 => [
        'default_mode'  => 'resource',
        'default_shape' => 'default',
        'shapes'        => ['default' => ['includes' => [
            'component' => ['loads' => ['component']],
        ]]],
    ],
    'lahatre.catalog.bundles.items.update' => [
        'default_mode'  => 'resource',
        'default_shape' => 'default',
        'shapes'        => ['default' => ['includes' => [
            'component' => ['loads' => ['component']],
        ]]],
    ],
    'lahatre.catalog.bundles.stock-operations.index'    => [],
    'lahatre.catalog.bundles.stock-operations.store'    => [],
    'lahatre.catalog.bundles.stock-operations.show'     => [],
    'lahatre.catalog.bundles.stock-operations.complete' => [],
    'lahatre.catalog.categories.destroy'                => [],
    'lahatre.catalog.options.destroy'                   => [],
    'lahatre.catalog.options.values.destroy'            => [],
    'lahatre.catalog.products.destroy'                  => [],
    'lahatre.catalog.products.variants.destroy'         => [],
    'lahatre.catalog.bundles.destroy'                   => [],
    'lahatre.catalog.stock-locations.destroy'           => [],
    'lahatre.catalog.bundles.items.destroy'             => [],
];
