<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Audit Configuration
    |--------------------------------------------------------------------------
    */
    'extra_namespaces' => [
        'App\Models',
    ],

    'ignored_models' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Integrity Configuration
    |--------------------------------------------------------------------------
    */
    'ignored_tables' => [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'personal_access_tokens',
        'password_reset_tokens',
    ],

    'tenancy_ignored_tables' => [
        'organization_organizations',
        'users',
        'iam_users',
        'iam_roles',
        'iam_permissions',
        'iam_model_has_roles',
        'iam_model_has_permissions',
        'iam_role_has_permissions',
        'master_currencies',
        'master_units', // TODO: to be corrected
        'master_unit_groups',
        'catalog_product_categories',
        'catalog_variant_option_value',
        'inventory_items',
        'inventory_locations',
        'inventory_movements',
        'inventory_stocks',
        'inventory_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Uniqueness Exemptions
    |--------------------------------------------------------------------------
    |
    | Define unique indexes that are allowed to be global (not scoped by organization_id)
    | even if the table has an organization_id column.
    | Format: 'table_name' => ['index_name1', 'index_name2']
    |
    */
    'exempt_global_uniqueness' => [
        // 'catalog_products' => ['catalog_products_handle_unique'],
    ],

    'composite_pkey' => [
        'iam_model_has_permissions',
        'iam_model_has_roles',
        'iam_role_has_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Index Names
    |--------------------------------------------------------------------------
    |
    | For index names that would exceed the database's maximum length (e.g. 63
    | chars for Postgres), you can define a shorter alias here.
    | Format: 'table_name' => ['generated_name' => 'shorter_alias']
    |
    */
    'custom_index_names' => [
        'catalog_prices' => [
            'catalog_prices_currency_code_min_quantity_organization_id_priceable_id_priceable_type_step_unique' => 'catalog_prices_unique_idx',
        ],
        'inventory_stocks' => [
            'inventory_stocks_expiration_date_index'     => 'inventory_stocks_expiration_date_active_index',
            'inventory_stocks_item_id_location_id_index' => [
                'inventory_stocks_item_id_location_id_active_index',
                'inventory_stocks_location_id_item_id_active_index',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Delete Uniqueness Configuration
    |--------------------------------------------------------------------------
    |
    | Define columns that should NOT follow the 'WHERE deleted_at IS NULL'
    | constraint for unique indexes even if the table has soft deletes.
    | Format: 'table_name' => ['column1', 'column2']
    |
    */
    'ignored_soft_delete_partial_index' => [
        'users'                    => ['email'],
        'iam_users'                => ['email'],
        'inventory_items'          => ['sku'],
        'master_units'             => ['code'],
        'catalog_product_variants' => ['sku'],
    ],
];
