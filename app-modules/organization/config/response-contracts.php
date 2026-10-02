<?php

declare(strict_types=1);

$settingsContracts = array_fill_keys(
    ['lahatre.organization.settings.show', 'lahatre.organization.settings.update'],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['organization'],
        ]],
    ],
);

return [
    ...$settingsContracts,
    'lahatre.organization.exchange-rates.index'   => [],
    'lahatre.organization.exchange-rates.show'    => [],
    'lahatre.organization.exchange-rates.store'   => [],
    'lahatre.organization.exchange-rates.update'  => [],
    'lahatre.organization.exchange-rates.destroy' => [],
];
