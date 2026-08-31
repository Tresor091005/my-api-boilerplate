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
    'ignored_soft_delete_partial_columns' => [
        'users'              => ['email'],
        'iam_users'          => ['email'],
        'inventory_items'    => ['sku'],
        'master_units'       => ['code'],
        'master_currencies'  => ['code'],
        'catalog_items'      => ['sku'],
        'catalog_categories' => ['handle'],
        'catalog_products'   => ['handle'],
        'catalog_bundles'    => ['handle'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Delete Partial Index Exemptions
    |--------------------------------------------------------------------------
    |
    | These indexes are required as targets for composite tenant foreign keys.
    | PostgreSQL does not allow foreign keys to reference partial unique indexes.
    | A unique index used as a foreign-key target may therefore be exempted
    | from the required WHERE deleted_at IS NULL predicate.
    |
    */
    'ignored_soft_delete_partial_indexes' => [
        'catalog_option_values'    => ['catalog_option_values_organization_id_id_unique'],
        'catalog_options'          => ['catalog_options_organization_id_id_unique'],
        'catalog_items'            => ['catalog_items_organization_id_id_unique'],
        'catalog_product_variants' => ['catalog_product_variants_organization_id_id_unique'],
        'catalog_categories'       => ['catalog_categories_organization_id_id_unique'],
        'catalog_products'         => ['catalog_products_organization_id_id_unique'],
        'iam_organization_members' => ['iam_organization_members_organization_id_id_unique'],
        'master_notes'             => ['master_notes_organization_id_id_unique'],
        'master_labels'            => ['master_labels_organization_id_id_unique'],
        'inventory_items'          => ['inventory_items_organization_id_id_unique'],
        'inventory_locations'      => ['inventory_locations_organization_id_id_unique'],
        'inventory_stocks'         => [
            'inventory_stocks_organization_id_id_unique',
            'inventory_stocks_aggregate_identity_unique',
        ],
    ],
];
