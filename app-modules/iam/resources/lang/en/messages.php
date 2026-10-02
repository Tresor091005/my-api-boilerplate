<?php

declare(strict_types=1);

return [
    'session_device' => [
        'phone'    => 'Phone',
        'tablet'   => 'Tablet',
        'computer' => 'Computer',
    ],
    'invitation' => [
        'accepted' => 'Organization joined successfully. Sign in to access it.',
        'joined'   => 'Organization joined successfully. Select a member role to access it.',
    ],
    'auth' => [
        'google_linked'            => 'Google was linked to your account successfully.',
        'organization_created'     => 'Organization created successfully. Select a member role to access it.',
        'logged_out'               => 'Successfully logged out.',
        'role_switched'            => 'Successfully switched role.',
        'registration_link_sent'   => 'If this address can register an organization, an email has been sent.',
        'organization_registered'  => 'Organization created successfully. Sign in to access it.',
        'login_code_sent'          => 'If this email address is available, a sign-in code has been sent.',
        'incoherent_auth_metadata' => 'Incoherent AuthContext metadata for user :user_id.',
    ],
];
