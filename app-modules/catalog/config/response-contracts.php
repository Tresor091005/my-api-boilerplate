<?php

declare(strict_types=1);

$categoryContracts = array_fill_keys(
    [
        'lahatre.catalog.categories.index',
        'lahatre.catalog.categories.show',
        'lahatre.catalog.categories.store',
        'lahatre.catalog.categories.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'bloodline' => ['loads' => ['bloodline']],
            ],
        ]],
    ],
);

$optionContracts = array_fill_keys(
    [
        'lahatre.catalog.options.index',
        'lahatre.catalog.options.show',
        'lahatre.catalog.options.store',
        'lahatre.catalog.options.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'values' => ['loads' => ['values']],
            ],
        ]],
    ],
);

$optionValueContracts = array_fill_keys(
    [
        'lahatre.catalog.options.values.index',
        'lahatre.catalog.options.values.show',
        'lahatre.catalog.options.values.store',
        'lahatre.catalog.options.values.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'option' => ['loads' => ['option']],
            ],
        ]],
    ],
);

$productContracts = array_fill_keys(
    [
        'lahatre.catalog.products.index',
        'lahatre.catalog.products.show',
        'lahatre.catalog.products.store',
        'lahatre.catalog.products.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'categories' => ['loads' => ['categories']],
                'options'    => ['loads' => ['optionValues.option']],
                'variants'   => ['loads' => ['variants.catalogItem', 'variants.product', 'variants.optionValues.option']],
            ],
        ]],
    ],
);

$variantContracts = array_fill_keys(
    [
        'lahatre.catalog.products.variants.index',
        'lahatre.catalog.products.variants.show',
        'lahatre.catalog.products.variants.store',
        'lahatre.catalog.products.variants.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['catalogItem', 'product', 'optionValues.option'],
            'includes'       => [
                'labels'     => ['loads' => ['labels']],
                'unit_group' => ['loads' => ['catalogItem.unitGroup']],
                'units'      => ['loads' => ['catalogItem.unitGroup.units']],
                'inventory'  => ['loads' => ['catalogItem.inventoryItem.stockSummaries']],
            ],
        ]],
    ],
);

$bundleResourceContracts = array_fill_keys(
    [
        'lahatre.catalog.bundles.index',
        'lahatre.catalog.bundles.show',
        'lahatre.catalog.bundles.store',
        'lahatre.catalog.bundles.update',
    ],
    [
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
    ],
);

$bundleItemContracts = array_fill_keys(
    [
        'lahatre.catalog.bundles.items.store',
        'lahatre.catalog.bundles.items.update',
    ],
    [
        'default_mode'  => 'resource',
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'component' => ['loads' => ['component']],
            ],
        ]],
    ],
);

// These delete endpoints intentionally return no representation and therefore use the method default 204.
$noContentContracts = array_fill_keys(
    [
        'lahatre.catalog.categories.destroy',
        'lahatre.catalog.options.destroy',
        'lahatre.catalog.options.values.destroy',
        'lahatre.catalog.products.destroy',
        'lahatre.catalog.products.variants.destroy',
        'lahatre.catalog.bundles.destroy',
        'lahatre.catalog.bundles.items.destroy',
    ],
    [],
);

return [
    ...$categoryContracts,
    ...$optionContracts,
    ...$optionValueContracts,
    ...$productContracts,
    ...$variantContracts,
    ...$bundleResourceContracts,
    ...$bundleItemContracts,
    ...$noContentContracts,
];
