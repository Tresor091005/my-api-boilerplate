<?php

declare(strict_types=1);

// Legacy contracts for the registry tests: no real route declares a shape any more.
return [
    '_shapes' => [
        'customer' => [
            'required_loads' => ['organization'],
            'includes'       => [
                'addresses'             => ['loads' => ['addresses']],
                'files.profile_picture' => ['loads' => ['profilePictureFileAttachments.file']],
            ],
        ],
    ],
    'fixture.customers.show'   => ['default_shape' => 'default', 'shapes' => ['default' => ['ref' => 'customer']]],
    'fixture.customers.store'  => ['default_shape' => 'default', 'shapes' => ['default' => ['ref' => 'customer']]],
    'fixture.customers.update' => ['default_shape' => 'default', 'shapes' => ['default' => ['ref' => 'customer']]],
    'fixture.customers.export' => [],
];
