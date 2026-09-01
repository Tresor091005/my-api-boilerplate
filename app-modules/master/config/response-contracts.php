<?php

declare(strict_types=1);

// These routes intentionally return no body and therefore use the method default 204.
$emptyContracts = array_fill_keys(
    [
        'lahatre.master.labels.destroy',
        'lahatre.master.labels.reorder',
        'lahatre.master.notes.visibility.update',
        'lahatre.master.notes.destroy',
        'lahatre.master.notes.pin',
        'lahatre.master.notes.unpin',
        'lahatre.master.notes.mentions.store',
        'lahatre.master.notes.mentions.destroy',
        'lahatre.master.notes.mentions.read',
    ],
    [],
);

$currencyContracts = array_fill_keys(
    ['lahatre.master.currencies.index'],
    [
    ],
);

$labelCollectionContracts = array_fill_keys(
    [
        'lahatre.master.labels.index',
        'lahatre.master.labels.store',
    ],
    [
    ],
);

$labelResourceContracts = array_fill_keys(
    ['lahatre.master.labels.update'],
    [
    ],
);

$noteCollectionContracts = array_fill_keys(
    ['lahatre.master.notes.index'],
    [
    ],
);

$noteResourceContracts = array_fill_keys(
    [
        'lahatre.master.notes.store',
        'lahatre.master.notes.update',
    ],
    [
    ],
);

return [
    ...$emptyContracts,
    ...$currencyContracts,
    ...$labelCollectionContracts,
    ...$labelResourceContracts,
    ...$noteCollectionContracts,
    ...$noteResourceContracts,
    'lahatre.master.units.index' => [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'group' => ['loads' => ['group']],
            ],
        ]],
    ],
    'lahatre.master.units.upsert' => [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'units'       => ['loads' => ['units']],
                'units.group' => ['loads' => ['units.group']],
            ],
        ]],
    ],
    'lahatre.master.notes.show' => [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'children' => ['loads' => ['replies']],
                'mentions' => ['loads' => ['mentions']],
            ],
        ]],
    ],
];
