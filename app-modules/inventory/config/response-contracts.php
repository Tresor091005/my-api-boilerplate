<?php

declare(strict_types=1);

$itemContracts = array_fill_keys(
    [
        'lahatre.inventory.items.index',
        'lahatre.inventory.items.show',
        'lahatre.inventory.items.update',
    ],
    [
        'default_shape' => 'item',
        'shapes'        => ['item' => [
            'includes' => [
                'itemable'         => ['loads' => ['itemable']],
                'stocks'           => ['loads' => ['stocks']],
                'movements'        => ['loads' => ['movements']],
                'stocks.location'  => ['loads' => ['stocks.location']],
                'stocks.unit'      => ['loads' => ['stocks.unit']],
                'stocks.currency'  => ['loads' => ['stocks.currency']],
                'stocks.movements' => ['loads' => ['stocks.movements']],
            ],
        ]],
    ],
);

$locationContracts = array_fill_keys(
    [
        'lahatre.inventory.locations.index',
        'lahatre.inventory.locations.show',
    ],
    [
        'default_shape' => 'location',
        'shapes'        => ['location' => [
            'includes' => [
                'external'         => ['loads' => ['external']],
                'stocks'           => ['loads' => ['stocks']],
                'movements'        => ['loads' => ['movements']],
                'stocks.location'  => ['loads' => ['stocks.location']],
                'stocks.unit'      => ['loads' => ['stocks.unit']],
                'stocks.currency'  => ['loads' => ['stocks.currency']],
                'stocks.movements' => ['loads' => ['stocks.movements']],
            ],
        ]],
    ],
);

$movementContracts = array_fill_keys(
    [
        'lahatre.inventory.items.movements.index',
        'lahatre.inventory.locations.movements.index',
    ],
    [
        'default_shape' => 'movement',
        'shapes'        => ['movement' => [
            'includes' => [
                'stock'    => ['loads' => ['stock']],
                'location' => ['loads' => ['location']],
                'unit'     => ['loads' => ['unit']],
                'currency' => ['loads' => ['currency']],
            ],
        ]],
    ],
);

$emptyContracts = array_fill_keys(
    [
        'lahatre.inventory.items.locations.lots.index',
        'lahatre.inventory.items.stock.show',
        'lahatre.inventory.items.value.show',
        'lahatre.inventory.locations.stock.show',
        'lahatre.inventory.locations.value.show',
        'lahatre.inventory.stock.expiring.index',
        'lahatre.inventory.stock.summary.index',
    ],
    [],
);

$transactionContracts = array_fill_keys(
    [
        'lahatre.inventory.transactions.index',
        'lahatre.inventory.transactions.show',
    ],
    [
        'default_shape' => 'transaction',
        'shapes'        => ['transaction' => [
            'includes' => [
                'movements'          => ['loads' => ['movements']],
                'movements.stock'    => ['loads' => ['movements.stock']],
                'movements.location' => ['loads' => ['movements.location']],
                'movements.unit'     => ['loads' => ['movements.unit']],
                'movements.currency' => ['loads' => ['movements.currency']],
            ],
        ]],
    ],
);

$stockContracts = array_fill_keys(
    ['lahatre.inventory.stocks.update'],
    [
        'default_shape' => 'projection',
        // Stock mutations return no body by default; resource mode exposes scalar stock data and its optional relations.
        'shapes' => ['projection' => [
            'includes' => [
                'location'  => ['loads' => ['location']],
                'unit'      => ['loads' => ['unit']],
                'currency'  => ['loads' => ['currency']],
                'movements' => ['loads' => ['movements']],
            ],
        ]],
    ],
);

return [
    ...$itemContracts,
    ...$emptyContracts,
    ...$movementContracts,
    ...$locationContracts,
    ...$stockContracts,
    ...$transactionContracts,
];
