<?php

declare(strict_types=1);

return [
    'helpers_missing_descriptions' => 'Every project helper must have an extractable PHPDoc description.',
    'child_limits_sync'            => [
        'description' => 'Align the database triggers with config/child-limits.php (--check only reports the differences)',
        'synced'      => 'The child limit triggers match config/child-limits.php.',
        'in_sync'     => 'The database matches config/child-limits.php.',
        'missing'     => 'Missing trigger :name on :table.',
        'obsolete'    => 'Trigger :name on :table is no longer declared.',
    ],
    'api_resource' => [
        'already_exists'       => 'The :type already exists.',
        'created_successfully' => 'The :type was created successfully.',
        'reflection_failed'    => 'Could not reflect model :model. Declaring the id field only.',
        'casts_failed'         => 'An error occurred while processing casts for model :model. Declaring the id field only. Error: :error',
    ],
];
