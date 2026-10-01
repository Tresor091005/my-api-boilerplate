<?php

declare(strict_types=1);

return [
    'geoip' => [
        'configuration_missing' => 'Configure MAXMIND_ACCOUNT_ID, MAXMIND_LICENSE_KEY, and a local database path before updating GeoLite2 City.',
        'download_failed'       => 'GeoLite2 City download failed. Check MaxMind credentials, account access, and network availability.',
        'invalid_archive'       => 'The downloaded archive does not contain a valid City database.',
        'storage_unavailable'   => 'The GeoLite2 City database directory is not writable.',
    ],
    'auth' => [
        'invalid_code'            => 'The sign-in code is invalid, expired, or no longer available.',
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
    ],
    'email_account' => [
        'unavailable_email'      => 'This email address cannot be used for registration.',
        'user_details_required'  => 'User details are required for a new account.',
        'user_details_forbidden' => 'User details cannot be changed during email onboarding.',
    ],
    'organization_member' => [
        'unavailable' => 'Member is not available in this organization.',
        'owner'       => 'The organization owner cannot be removed or deactivated.',
    ],
    'member_role' => [
        'roles_unavailable'             => 'One or more organization roles are unavailable.',
        'already_assigned'              => 'One or more roles are already assigned to this member.',
        'assignments_unavailable'       => 'One or more role assignments are unavailable for this member.',
        'owner_administrator_protected' => 'The organization owner must retain their Administrator role.',
    ],
    'invitation' => [
        'unavailable'       => 'Invitation is not available in this organization.',
        'invalid_token'     => 'The invitation token is invalid or expired.',
        'already_accepted'  => 'An accepted invitation cannot be changed.',
        'already_member'    => 'This user is already a member of the organization.',
        'roles_unavailable' => 'One or more offered roles are unavailable. Replace the invitation roles before continuing.',
        'unavailable_email' => 'This email address cannot be invited.',
    ],
    'migration' => [
        'config_not_loaded'   => 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.',
        'config_not_found'    => 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.',
        'team_key_not_loaded' => 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.',
    ],
];
