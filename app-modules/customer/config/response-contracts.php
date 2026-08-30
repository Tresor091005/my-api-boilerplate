<?php

declare(strict_types=1);

$customerContracts = array_fill_keys(
    [
        'lahatre.customer.customers.index',
        'lahatre.customer.customers.show',
        'lahatre.customer.customers.store',
        'lahatre.customer.customers.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'addresses' => ['loads' => ['addresses']],
                'contacts'  => ['loads' => ['contacts']],
            ],
        ]],
    ],
);

$defaultModeResourceContracts = array_fill_keys(
    [
        'lahatre.customer.customers.addresses.store',
        'lahatre.customer.customers.addresses.update',
        'lahatre.customer.customers.contacts.store',
        'lahatre.customer.customers.contacts.update',
    ], [
        'default_mode' => 'resource',
    ]
);

$noContentContracts = array_fill_keys(
    [
        'lahatre.customer.customers.destroy',
        'lahatre.customer.customers.addresses.destroy',
        'lahatre.customer.customers.contacts.destroy',
    ],
    [],
);

return [
    ...$customerContracts,
    ...$defaultModeResourceContracts,
    ...$noContentContracts,
];
