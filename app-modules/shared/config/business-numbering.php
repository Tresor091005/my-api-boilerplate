<?php

declare(strict_types=1);

return [
    'invoice' => [
        'format'   => 'INV-{YEAR}-{SEQ}',
        'reset'    => 'yearly',
        'sequence' => [
            'start'    => 1,
            'pad'      => 6,
            'grouping' => null,
        ],
    ],
];
