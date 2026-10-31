<?php

declare(strict_types=1);

return [
    'organization_context_required'        => 'An active organization is required for this operation.',
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
    'child_limit' => [
        'reached' => 'No more than :max can be added here; remove some first.',
    ],
    'enum' => [
        'invalid_value' => "Invalid value ':value' for enum :enum_class.",
    ],
    'state_machine' => [
        'state_already_registered'         => 'The state :state is already registered.',
        'initial_state_not_registered'     => 'The initial state :state is not registered.',
        'transition_target_not_registered' => 'The transition target :target is not registered.',
        'state_not_found'                  => 'The state :state does not exist.',
        'actor_not_ready'                  => 'The state machine actor must be started or restored before use.',
        'transition_not_allowed'           => 'The event :event is not allowed from the state :state.',
        'source_state_required'            => 'A source state must be selected before defining a transition.',
        'definition_frozen'                => 'The state machine definition cannot be changed after it is frozen.',
        'state_type_mismatch'              => 'The state enum :actual does not match the machine state enum :expected.',
        'event_type_mismatch'              => 'The event enum :actual does not match the machine event enum :expected.',
        'invalid_event_enum'               => 'The configured event type is not a valid enum.',
        'invalid_context_class'            => 'The configured context type :context is not a class.',
        'context_required'                 => 'The state machine requires a context of type :expected.',
        'context_type_mismatch'            => 'The context :actual does not match the machine context :expected.',
        'context_not_declared'             => 'The state machine declares no context, but :actual was given.',
    ],
];
