<?php

declare(strict_types=1);

return [
    'skip_models' => [
        'billing_plan',
        'billing_plan_version',
        'billing_plan_version_feature',
        'billing_checkout',
        'billing_trial',
        'catalog_bundle_item',
        'catalog_option_value',
        'catalog_product_variant',
        'catalog_stock_transfer_line',
        'iam_platform_user',
        'master_address',
        'master_contact',
    ],
    'additional_actions' => [
        'billing_subscription' => [
            'pay',
        ],
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
