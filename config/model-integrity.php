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
        'master_taggables',
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
        'inventory_locations' => [
            'inventory_locations_organization_id_external_id_external_type_unique' => 'inventory_locations_org_external_unique',
        ],
        'inventory_items' => [
            'inventory_items_organization_id_itemable_id_itemable_type_unique' => 'inventory_items_org_itemable_unique',
        ],
        'pricing_price_entries' => [
            'pricing_price_entries_organization_id_context_currency_code_is_active_index' => 'pricing_pe_org_ctx_currency_active_idx',
            'pricing_price_entries_priceable_type_priceable_id_priceable_kind_index'      => 'pricing_pe_priceable_scope_idx',
        ],
        'pricing_priceable_group_members' => [
            'pricing_priceable_group_members_organization_id_group_id_priceable_type_priceable_id_unique' => 'pricing_pgm_org_group_target_unique',
            'pricing_priceable_group_members_priceable_type_priceable_id_index'                           => 'pricing_pgm_priceable_lookup_idx',
        ],
        'pricing_party_group_members' => [
            'pricing_party_group_members_organization_id_group_id_party_type_party_id_unique' => 'pricing_pagm_org_group_target_unique',
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
        'master_currencies'        => ['code'],
        'catalog_product_variants' => ['sku'],
        'catalog_categories'       => ['handle'],
        'catalog_products'         => ['handle'],
        'catalog_bundles'          => ['handle'],
    ],
];
