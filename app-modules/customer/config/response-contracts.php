<?php

declare(strict_types=1);

$customerCollection = [
    'default_shape' => 'default',
    'shapes'        => ['default' => ['includes' => [
        'addresses' => ['loads' => ['addresses']],
        'contacts'  => ['loads' => ['contacts']],
    ]]],
];

$customerResource = $customerCollection;

return [
    'lahatre.customer.customers.index'           => $customerCollection,
    'lahatre.customer.customers.show'            => $customerResource,
    'lahatre.customer.customers.store'           => $customerResource,
    'lahatre.customer.customers.update'          => $customerResource,
    'lahatre.customer.customers.addresses.store' => [
        'default_mode' => 'resource',
    ],
    'lahatre.customer.customers.addresses.update' => [
        'default_mode' => 'resource',
    ],
    'lahatre.customer.customers.contacts.store' => [
        'default_mode' => 'resource',
    ],
    'lahatre.customer.customers.contacts.update' => [
        'default_mode' => 'resource',
    ],
    // These delete endpoints intentionally return no body.
    'lahatre.customer.customers.destroy'           => [],
    'lahatre.customer.customers.addresses.destroy' => [],
    'lahatre.customer.customers.contacts.destroy'  => [],
];
