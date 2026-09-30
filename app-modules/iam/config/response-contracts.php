<?php

declare(strict_types=1);

$resourceModeContracts = array_fill_keys(
    [
        'lahatre.iam.auth.forgot-password',
        'lahatre.iam.auth.organization-registration-tokens.store',
        'lahatre.iam.auth.register',
        'lahatre.iam.invitations.accept',
        'lahatre.iam.auth.logout',
        'lahatre.iam.auth.reset-password',
    ],
    ['default_mode' => 'resource'],
);

$authResourceContracts = array_fill_keys(
    ['lahatre.iam.auth.login'],
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

$roleResourceContracts = array_fill_keys(
    [
        'lahatre.iam.roles.index',
        'lahatre.iam.roles.show',
        'lahatre.iam.roles.store',
        'lahatre.iam.roles.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'permissions' => ['loads' => ['permissions']],
            ],
        ]],
    ],
);

$invitationResourceContracts = array_fill_keys(
    [
        'lahatre.iam.invitations.index',
        'lahatre.iam.invitations.show',
        'lahatre.iam.invitations.store',
        'lahatre.iam.invitations.roles.update',
        'lahatre.iam.invitations.resend',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'includes' => [
                'roles' => ['loads' => ['roles']],
            ],
        ]],
    ],
);

$organizationMemberResourceContracts = array_fill_keys(
    [
        'lahatre.iam.organization-members.index',
        'lahatre.iam.organization-members.show',
        'lahatre.iam.organization-members.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['user'],
            'includes'       => [
                'member_roles' => ['loads' => ['memberRoles.role']],
            ],
        ]],
    ],
);

$memberRoleResourceContracts = array_fill_keys(
    [
        'lahatre.iam.organization-members.member-roles.store',
        'lahatre.iam.organization-members.member-roles.update',
    ],
    [
        'default_shape' => 'default',
        'shapes'        => ['default' => [
            'required_loads' => ['role'],
        ]],
    ],
);

return [
    ...$resourceModeContracts,
    ...$authResourceContracts,
    ...$userResourceContracts,
    ...$roleResourceContracts,
    ...$invitationResourceContracts,
    ...$organizationMemberResourceContracts,
    ...$memberRoleResourceContracts,
    // GET already defaults to a resource; permission output has no relations or alternate shapes.
    'lahatre.iam.auth.current-permissions'                  => [],
    'lahatre.iam.permissions.index'                         => [],
    'lahatre.iam.invitations.destroy'                       => [],
    'lahatre.iam.roles.destroy'                             => [],
    'lahatre.iam.organization-members.destroy'              => [],
    'lahatre.iam.organization-members.member-roles.destroy' => [],
];
