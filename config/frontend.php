<?php

declare(strict_types=1);

return [
    'url'                            => env('FRONTEND_URL', 'http://localhost:28421'),
    'organization_registration_path' => '/auth/register',
    'invitation_acceptance_path'     => '/auth/accept-invitation',
];
