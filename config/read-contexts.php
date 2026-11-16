<?php

declare(strict_types=1);

/*
 * Which write opens which read, as which view. Pinned by tests/Feature/Architecture/ReadContextsTest.php.
 */
return [
    // the write being prepared => route => what it opens
    'customer_customer.create' => [
        'lahatre.catalog.price-levels.index' => ['view' => 'reference', 'replaces' => 'catalog_price_level.list'],
        'lahatre.catalog.price-levels.show'  => ['view' => 'reference', 'replaces' => 'catalog_price_level.retrieve'],
    ],
    // same reads as another context
    'customer_customer.update' => 'customer_customer.create',
];
