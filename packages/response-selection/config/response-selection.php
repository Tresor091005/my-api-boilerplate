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
        // children one parent renders in a list include, unless the include sets its own `limit:`
        'default_include_items' => 100,
        // highest `limit:` an include may set
        'max_include_items' => 1000,
    ],

    // Report a column or relation read without being loaded, instead of failing the request.
    // null: in production only.
    'tolerate_model_violations' => null,
];
