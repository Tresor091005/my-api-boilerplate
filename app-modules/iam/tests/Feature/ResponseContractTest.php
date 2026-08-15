<?php

declare(strict_types=1);

use Lahatre\Shared\Http\Responses\ResponseMode;
use Lahatre\Shared\Registries\ResponseContractRegistry;

it('keeps payload-producing IAM endpoints resource responses by default', function (): void {
    $registry = app(ResponseContractRegistry::class);

    foreach ([
        'lahatre.iam.auth.forgot-password',
        'lahatre.iam.auth.login',
        'lahatre.iam.auth.logout',
        'lahatre.iam.auth.reset-password',
        'lahatre.iam.auth.switch-member-role',
    ] as $routeName) {
        expect($registry->forRoute($routeName)?->resolveMode(null, 'POST'))
            ->toBe(ResponseMode::Resource);
    }
});

it('declares the user relationships required by IAM resources', function (): void {
    $registry = app(ResponseContractRegistry::class);

    foreach ([
        'lahatre.iam.auth.login',
        'lahatre.iam.auth.me',
        'lahatre.iam.auth.switch-member-role',
    ] as $routeName) {
        expect($registry->forRoute($routeName)?->resolveShape(null))
            ->not->toBeNull()
            ->and($registry->forRoute($routeName)?->resolveShape(null)->relationsToLoad([]))
            ->toContain('organizationMemberships.memberRoles.role');
    }
});
