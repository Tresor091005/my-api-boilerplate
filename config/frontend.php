<?php

declare(strict_types=1);

return [
    'url'                            => env('FRONTEND_URL', 'http://localhost:3000'),
    'organization_registration_path' => '/auth/register',
    'invitation_acceptance_path'     => '/auth/accept-invitation',
    'reset_password_path'            => '/auth/reset-password',
];
