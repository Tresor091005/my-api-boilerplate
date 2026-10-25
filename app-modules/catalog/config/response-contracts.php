<?php

declare(strict_types=1);

// Every other catalog route answers through ApiResponse (response selection). These two stream a
// file, so they keep an empty legacy contract until the legacy middleware is removed.
return [
    'lahatre.catalog.products.files.content' => [],
    'lahatre.catalog.services.files.content' => [],
];
