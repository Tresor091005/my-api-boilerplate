<?php

declare(strict_types=1);

return [
    'organization_context_required'     => 'An active organization is required for this operation.',
    'response_contract_collision'       => 'Response contract routes are already registered: :routes.',
    'response_contract_missing'         => 'The API route [:route] has no registered response contract.',
    'response_contract_config_invalid'  => 'Response contract configuration [:path] must return an array.',
    'response_contract_shapes_invalid'  => 'Response contract shapes in [:path] must be defined as an array.',
    'response_contract_cache_invalid'   => 'The response contract cache must return an array.',
    'response_shape_reference_invalid'  => 'Response shape reference [:reference] must be a non-empty string.',
    'response_shape_reference_missing'  => 'Response shape reference [:reference] does not exist.',
    'response_shape_reference_cycle'    => 'Response shape reference cycle detected: :references.',
    'response_shape_fields_unsupported' => 'Response shape [:shape] does not support field selection yet.',
    'enum'                              => [
        'invalid_value' => "Invalid value ':value' for enum :enum_class.",
    ],
];
