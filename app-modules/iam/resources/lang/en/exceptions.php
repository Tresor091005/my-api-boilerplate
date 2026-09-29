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
    'migration' => [
        'config_not_loaded'   => 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.',
        'config_not_found'    => 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.',
        'team_key_not_loaded' => 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.',
    ],
];
