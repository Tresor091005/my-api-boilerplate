<?php

declare(strict_types=1);

$movementContracts = array_fill_keys(
    [
        'lahatre.inventory.movements.index',
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
        'lahatre.inventory.stocks.index',
        'lahatre.inventory.stocks.summary.index',
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
    ...$emptyContracts,
    ...$movementContracts,
    ...$stockContracts,
    ...$transactionContracts,
];
