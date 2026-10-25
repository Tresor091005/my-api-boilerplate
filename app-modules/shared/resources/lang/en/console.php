<?php

declare(strict_types=1);

return [
    'helpers_missing_descriptions' => 'Every project helper must have an extractable PHPDoc description.',
    'api_resource'                 => [
        'already_exists'       => 'The :type already exists.',
        'created_successfully' => 'The :type was created successfully.',
        'reflection_failed'    => 'Could not reflect model :model. Declaring the id field only.',
        'casts_failed'         => 'An error occurred while processing casts for model :model. Declaring the id field only. Error: :error',
    ],
];
