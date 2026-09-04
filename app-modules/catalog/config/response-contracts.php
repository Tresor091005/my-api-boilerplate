<?php

declare(strict_types=1);

$categoryCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'bloodline' => ['loads' => ['bloodline']],
    ]]],
];

$optionCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'values' => ['loads' => ['values']],
    ]]],
];

$optionValueCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'option' => ['loads' => ['option']],
    ]]],
];

$productCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'categories' => ['loads' => ['categories']],
        'options'    => ['loads' => ['optionValues.option']],
        'variants'   => ['loads' => ['variants.catalogItem', 'variants.product', 'variants.optionValues.option']],
    ]]],
];

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

$stockLocationCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => [
        'required_loads' => ['inventoryLocation'],
        'includes'       => [
            'address' => ['loads' => ['address']],
        ],
    ]],
];

$stockTransferResource = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'lines' => ['loads' => ['lines']],
        'item'  => ['loads' => ['lines.item']],
    ]]],
];

return [
    'lahatre.catalog.categories.index'                    => $categoryCollection,
    'lahatre.catalog.categories.show'                     => $categoryCollection,
    'lahatre.catalog.categories.store'                    => $categoryCollection,
    'lahatre.catalog.categories.update'                   => $categoryCollection,
    'lahatre.catalog.options.index'                       => $optionCollection,
    'lahatre.catalog.options.show'                        => $optionCollection,
    'lahatre.catalog.options.store'                       => $optionCollection,
    'lahatre.catalog.options.update'                      => $optionCollection,
    'lahatre.catalog.options.values.index'                => $optionValueCollection,
    'lahatre.catalog.options.values.store'                => $optionValueCollection,
    'lahatre.catalog.options.values.show'                 => $optionValueCollection,
    'lahatre.catalog.options.values.update'               => $optionValueCollection,
    'lahatre.catalog.products.index'                      => $productCollection,
    'lahatre.catalog.products.show'                       => $productCollection,
    'lahatre.catalog.products.store'                      => $productCollection,
    'lahatre.catalog.products.update'                     => $productCollection,
    'lahatre.catalog.products.variants.index'             => $variantCollection,
    'lahatre.catalog.products.variants.activation.update' => [],
    'lahatre.catalog.products.variants.store'             => $variantCollection,
    'lahatre.catalog.products.variants.show'              => $variantCollection,
    'lahatre.catalog.products.variants.update'            => $variantCollection,
    'lahatre.catalog.bundles.index'                       => $bundleCollection,
    'lahatre.catalog.bundles.show'                        => $bundleCollection,
    'lahatre.catalog.bundles.store'                       => $bundleCollection,
    'lahatre.catalog.bundles.update'                      => $bundleCollection,
    'lahatre.catalog.stock-locations.index'               => $stockLocationCollection,
    'lahatre.catalog.stock-locations.show'                => $stockLocationCollection,
    'lahatre.catalog.stock-locations.store'               => $stockLocationCollection,
    'lahatre.catalog.stock-locations.update'              => $stockLocationCollection,
    'lahatre.catalog.stock-transfers.index'               => $stockTransferResource,
    'lahatre.catalog.stock-transfers.show'                => $stockTransferResource,
    'lahatre.catalog.stock-transfers.store'               => $stockTransferResource,
    'lahatre.catalog.stock-transfers.update'              => $stockTransferResource,
    'lahatre.catalog.stock-transfers.complete'            => $stockTransferResource,
    'lahatre.catalog.stock-transfers.cancel'              => $stockTransferResource,
    'lahatre.catalog.stock-transfers.destroy'             => [],
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
