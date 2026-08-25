<?php

declare(strict_types=1);

return [
    'organization_context_required'        => 'An active organization is required for this operation.',
    'response_contract_collision'          => 'Response contract routes are already registered: :routes.',
    'response_contract_missing'            => 'The API route [:route] has no registered response contract.',
    'response_contract_config_invalid'     => 'Response contract configuration [:path] must return an array.',
    'response_contract_shapes_invalid'     => 'Response contract shapes in [:path] must be defined as an array.',
    'response_contract_cache_invalid'      => 'The response contract cache must return an array.',
    'response_shape_reference_invalid'     => 'Response shape reference [:reference] must be a non-empty string.',
    'response_shape_reference_missing'     => 'Response shape reference [:reference] does not exist.',
    'response_shape_reference_cycle'       => 'Response shape reference cycle detected: :references.',
    'response_shape_fields_unsupported'    => 'Response shape [:shape] does not support field selection yet.',
    'business_number_definition_not_found' => 'The requested business number definition does not exist.',
    'invalid_business_number_definition'   => 'The business number definition is invalid.',
    'business_number_reasons'              => [
        'format_empty'           => 'The format must be a non-empty string.',
        'reset_invalid'          => 'The reset must be never, daily, monthly, or yearly.',
        'sequence_invalid'       => 'The sequence must be an array.',
        'sequence_start_invalid' => 'The sequence start must be a positive integer.',
        'sequence_pad_invalid'   => 'The sequence padding must be a positive integer.',
        'grouping_invalid'       => 'The sequence grouping must contain a positive interval and a non-empty separator.',
        'seq_token_invalid'      => 'The format must contain the :token token exactly once.',
        'unsupported_tokens'     => 'The format contains unsupported tokens.',
        'unmatched_brace'        => 'The format contains an unmatched brace.',
        'invalid_token'          => 'The format contains an invalid token: :token.',
        'reset_format_invalid'   => 'The format date tokens do not match the configured reset period.',
        'counter_missing'        => 'The database counter did not return a value.',
        'invalid_format'         => 'Invalid format.',
    ],
    'enum' => [
        'invalid_value' => "Invalid value ':value' for enum :enum_class.",
    ],
];
