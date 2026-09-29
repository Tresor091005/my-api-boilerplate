<?php

declare(strict_types=1);

return [
    'auth' => [
        'invalid_login'           => 'Invalid login details.',
        'password_reset_failed'   => 'Password reset failed.',
        'invalid_session_context' => 'Invalid session context.',
    ],
    'role' => [
        'unavailable'             => 'Role is not available in this organization.',
        'system_role'             => 'System roles cannot be changed.',
        'name_taken'              => 'A role with this name already exists.',
        'permissions_unavailable' => 'One or more permissions are unavailable.',
        'assigned'                => 'A role assigned to a member cannot be deleted.',
    ],
    'organization_onboarding' => [
        'system_roles_unavailable'   => 'The system roles are not configured.',
        'invalid_registration_token' => 'The registration token is invalid or expired.',
        'unavailable_email'          => 'This email address cannot be used for registration.',
        'user_details_required'      => 'User details are required for a new account.',
        'user_details_forbidden'     => 'User details cannot be changed during organization registration.',
    ],
    'migration' => [
        'config_not_loaded'   => 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.',
        'config_not_found'    => 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.',
        'team_key_not_loaded' => 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.',
    ],
];
