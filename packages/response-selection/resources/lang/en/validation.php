<?php

declare(strict_types=1);

return [
    'invalid_type'                 => 'The :parameter parameter must be a string.',
    'invalid_characters'           => 'The :parameter parameter may only contain letters, digits, underscores, dots, commas and parentheses.',
    'unbalanced_parentheses'       => 'The :parameter parameter has unbalanced parentheses.',
    'nested_parentheses'           => 'Nested parentheses are not allowed in :parameter; use dotted paths (variants.optionValues(id)).',
    'invalid_path'                 => 'The include path ":path" is invalid.',
    'invalid_field'                => 'The field ":field" is invalid.',
    'fields_not_allowed_in_fields' => 'The fields parameter cannot contain parentheses or dots.',
    'depth_exceeded'               => 'The include path ":path" exceeds the maximum depth of :max.',
    'too_many_paths'               => 'At most :max include paths are allowed, :count given.',
    'too_many_fields'              => 'At most :max fields per node are allowed, :count given at ":path".',
    'invalid_mode'                 => 'The response parameter must be one of: :allowed.',
    'mode_not_allowed'             => 'The response mode ":mode" is not allowed on this endpoint.',
    'selection_without_resource'   => 'The :parameter parameter cannot be used when the response mode is none.',
    'for_invalid'                  => 'The for parameter must be a string of 1 to 100 letters, digits, underscores, dots or hyphens.',
    'for_unknown'                  => 'The purpose ":purpose" is not accepted here.',
    'selection_with_for'           => 'The :parameter parameter cannot be used together with for.',
    'unknown_field'                => 'The field ":path" does not exist.',
    'unknown_include'              => 'The include ":path" does not exist.',
    'include_is_fixed'             => 'The include ":path" has a fixed shape: select it without a field list or a deeper path.',
];
