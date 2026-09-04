<?php

declare(strict_types=1);

return [
    'skip_models' => [
        'catalog_bundle_item',
        'catalog_option_value',
        'catalog_product_variant',
        'catalog_stock_transfer_line',
        'master_address',
        'master_contact',
    ],
    'additional_actions' => [
        'catalog_bundle' => [
            'assemble',
            'manage_composition',
        ],
        'catalog_product' => [
            'create_variant',
            'update_variant',
            'delete_variant',
        ],
        'catalog_stock_transfer' => [
            'complete',
            'cancel',
        ],
        'master_note' => [
            'pin',
            'mention',
            'visibility_organization',
        ],
    ],
];
