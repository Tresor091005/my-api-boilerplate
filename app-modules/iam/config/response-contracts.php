<?php

declare(strict_types=1);

// Every other IAM route answers through ApiResponse (response selection). The routes below answer
// a payload built in code, so they keep a legacy contract until the legacy middleware is removed.
// They answer a body by default, although they are writes.
return [
    // a confirmation message, or the challenge to complete
    'lahatre.iam.auth.organization-registrations.store'       => ['default_mode' => 'resource'],
    'lahatre.iam.auth.organization-registration-tokens.store' => ['default_mode' => 'resource'],
    'lahatre.iam.auth.email-challenges.store'                 => ['default_mode' => 'resource'],
    'lahatre.iam.auth.google-challenges.store'                => ['default_mode' => 'resource'],
    'lahatre.iam.auth.google-identities.store'                => ['default_mode' => 'resource'],
    'lahatre.iam.auth.organizations.store'                    => ['default_mode' => 'resource'],
    'lahatre.iam.auth.invitations.accept'                     => ['default_mode' => 'resource'],
    'lahatre.iam.auth.logout'                                 => ['default_mode' => 'resource'],
    // the AuthResource payload: an access token and the account it belongs to
    'lahatre.iam.auth.email-challenge-verifications.store'  => ['default_mode' => 'resource'],
    'lahatre.iam.auth.google-challenge-verifications.store' => ['default_mode' => 'resource'],
    'lahatre.iam.invitations.accept'                        => ['default_mode' => 'resource'],
];
