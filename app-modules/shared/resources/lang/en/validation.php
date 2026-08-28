<?php

declare(strict_types=1);

return [
    'failed'                        => 'Validation failed',
    'rfc3339_utc'                   => 'The :attribute must be a valid UTC date and time in RFC 3339 format.',
    'bulk_exists'                   => 'One or more selected :attribute are invalid.',
    'bulk_unique'                   => 'The :attribute contains duplicate or already used values.',
    'response_mode_invalid'         => 'The response mode must be either [none] or [resource].',
    'response_resource_required'    => 'This endpoint requires a response resource.',
    'response_delete_forbidden'     => 'DELETE endpoints cannot return a response resource.',
    'response_shapes_unsupported'   => 'This endpoint does not support response shapes.',
    'response_shape_invalid'        => 'The requested response shape is not supported.',
    'response_includes_not_allowed' => 'The requested includes are not allowed for the [:shape] response shape: :includes.',
];
