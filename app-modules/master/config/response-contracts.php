<?php

declare(strict_types=1);

// These routes intentionally rely on method-derived response modes and define no response relations or shapes.
$emptyContracts = array_fill_keys(
    [
        'lahatre.master.currencies.index',
        'lahatre.master.labels.index',
        'lahatre.master.labels.store',
        'lahatre.master.labels.update',
        'lahatre.master.labels.destroy',
        'lahatre.master.labels.reorder',
        'lahatre.master.notes.index',
        'lahatre.master.notes.store',
        'lahatre.master.notes.update',
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

return [
    ...$emptyContracts,
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
