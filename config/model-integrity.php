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
        'catalog_product_categories',
        'catalog_variant_option_value',
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
        'master_unit_groups' => ['master_unit_groups_name_unique'],
        'master_units'       => [
            'master_units_code_unique',
            'master_units_group_id_ratio_unique',
        ],
    ],

    'composite_pkey' => [
        'iam_model_has_permissions',
        'iam_model_has_roles',
        'iam_role_has_permissions',
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
        'master_currencies'        => ['code'],
        'catalog_product_variants' => ['sku'],
        'catalog_categories'       => ['handle'],
        'catalog_products'         => ['handle'],
        'catalog_bundles'          => ['handle'],
        'master_tags'              => ['organization_id'],
    ],
];
