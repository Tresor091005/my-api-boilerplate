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
                'variants'   => ['loads' => ['variants.product', 'variants.optionValues.option']],
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
            'required_loads' => ['product', 'optionValues.option'],
            'includes'       => [
                'unit_group' => ['loads' => ['unitGroup']],
                'units'      => ['loads' => ['unitGroup.units']],
                'labels'     => ['loads' => ['labels']],
                'inventory'  => ['loads' => ['inventoryItem.stockSummaries']],
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
    ],
    [],
);

return [
    ...$categoryContracts,
    ...$optionContracts,
    ...$optionValueContracts,
    ...$productContracts,
    ...$variantContracts,
    ...$noContentContracts,
];
