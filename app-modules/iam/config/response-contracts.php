<?php

declare(strict_types=1);

$resourceModeContracts = array_fill_keys(
    [
        'lahatre.iam.auth.forgot-password',
        'lahatre.iam.auth.logout',
        'lahatre.iam.auth.reset-password',
    ],
    ['default_mode' => 'resource'],
);

$authResourceContracts = array_fill_keys(
    [
        'lahatre.iam.auth.login',
        'lahatre.iam.auth.register',
    ],
    [
        'default_mode'  => 'resource',
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['organizationMemberships.memberRoles.role'],
        ]],
    ],
);

$userResourceContracts = array_fill_keys(
    [
        'lahatre.iam.auth.me',
        'lahatre.iam.auth.switch-member-role',
    ],
    [
        'default_mode'  => 'resource',
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['organizationMemberships.memberRoles.role'],
        ]],
    ],
);

return [
    ...$resourceModeContracts,
    ...$authResourceContracts,
    ...$userResourceContracts,
    // GET already defaults to a resource; permission output has no relations or alternate shapes.
    'lahatre.iam.auth.current-permissions' => [],
];
