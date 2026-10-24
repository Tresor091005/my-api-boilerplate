<?php

declare(strict_types=1);

return [
    'helpers_missing_descriptions' => 'Every project helper must have an extractable PHPDoc description.',
    'api_resource'                 => [
        'already_exists'       => 'The :type already exists.',
        'created_successfully' => 'The :type was created successfully.',
        'reflection_failed'    => 'Could not reflect model :model. Using the parent resource fallback.',
        'casts_failed'         => 'An error occurred while processing casts for model :model. Using the parent resource fallback. Error: :error',
    ],
    'response_contracts' => [
        'cached'  => 'Response contracts cached successfully.',
        'cleared' => 'Response contract cache cleared successfully.',
    ],
];
