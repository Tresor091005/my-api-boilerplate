<?php

declare(strict_types=1);

return [
    // How large a selection a client may ask for: beyond these, the request answers 422.
    'limits' => [
        // levels in one include path (`lines.product.category` is 3)
        'max_depth' => 4,
        // include paths, each parent counted (`lines.product` is 2)
        'max_paths' => 20,
        // fields listed for one resource
        'max_fields_per_node' => 50,
    ],

    // How many include levels the API documentation describes.
    'documented_include_depth' => 2,

    // Report a column or relation read without being loaded, instead of failing the request.
    // null: in production only.
    'tolerate_model_violations' => null,
];
