<?php

declare(strict_types=1);

$variantDetailContracts = array_fill_keys(
    [
        'lahatre.catalog.products.variants.show',
        'lahatre.catalog.products.variants.store',
        'lahatre.catalog.products.variants.update',
    ],
    [
        'default_shape' => 'detail',
        'shapes'        => ['detail' => ['ref' => 'variant_detail']],
    ],
);

return [
    '_shapes' => [
        'variant_detail' => [
            'required_loads' => ['product', 'optionValues.option', 'unitGroup'],
            'includes'       => [
                'tags'      => ['loads' => ['tags']],
                'inventory' => ['loads' => ['inventoryItem.stockSummaries']],
            ],
        ],
    ],

    'lahatre.catalog.categories.index'   => [],
    'lahatre.catalog.categories.store'   => [],
    'lahatre.catalog.categories.show'    => [],
    'lahatre.catalog.categories.update'  => [],
    'lahatre.catalog.categories.destroy' => [],

    'lahatre.catalog.options.index'   => [],
    'lahatre.catalog.options.store'   => [],
    'lahatre.catalog.options.show'    => [],
    'lahatre.catalog.options.update'  => [],
    'lahatre.catalog.options.destroy' => [],

    'lahatre.catalog.options.values.index'   => [],
    'lahatre.catalog.options.values.store'   => [],
    'lahatre.catalog.options.values.show'    => [],
    'lahatre.catalog.options.values.update'  => [],
    'lahatre.catalog.options.values.destroy' => [],

    'lahatre.catalog.products.index'   => [],
    'lahatre.catalog.products.store'   => [],
    'lahatre.catalog.products.show'    => [],
    'lahatre.catalog.products.update'  => [],
    'lahatre.catalog.products.destroy' => [],

    'lahatre.catalog.products.variants.index' => [
        'default_shape' => 'list',
        'shapes'        => [
            'list' => [
                'required_loads' => ['product', 'optionValues.option', 'unitGroup'],
                'includes'       => [
                    'tags' => ['loads' => ['tags']],
                ],
            ],
            'detail' => ['ref' => 'variant_detail'],
        ],
    ],
    ...$variantDetailContracts,
    'lahatre.catalog.products.variants.destroy' => [],
];
