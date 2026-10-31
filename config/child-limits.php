<?php

declare(strict_types=1);

/*
| The most children a parent row may have, by child table and foreign key.
| null means no limit, on purpose. ['max' => n, 'where' => 'sql'] only counts
| the children meeting the condition. PostgreSQL enforces every number through
| triggers that `child-limits:sync` keeps in line after each `migrate`.
| Numbers follow Shopify where it has one: 2 048 for what multiplies, 3 for
| dimensions, 250 for the lists attached to a record.
*/
return [
    'billing_plan_version_features' => ['plan_version_id' => null],
    'billing_plan_versions'         => ['plan_id' => null],
    'billing_subscriptions'         => ['plan_version_id' => null],

    'catalog_categories'                    => ['parent_id' => null],
    'catalog_bundle_items'                  => ['bundle_id' => 30],
    'catalog_bundle_stock_operations'       => ['bundle_id' => null],
    'catalog_option_values'                 => ['option_id' => 250],
    'catalog_product_categories'            => ['product_id' => 50, 'category_id' => null],
    'catalog_product_variants'              => ['product_id' => 2048],
    'catalog_service_deliverable_templates' => ['service_id' => 50],
    'catalog_stock_transfer_lines'          => ['stock_transfer_id' => 500],
    'catalog_variant_option_value'          => [
        'variant_id'      => 3,
        'product_id'      => null,
        'option_id'       => null,
        'option_value_id' => null,
    ],

    'iam_external_identities'   => ['user_id' => 10],
    'iam_invitation_roles'      => ['invitation_id' => 20],
    'iam_member_roles'          => ['member_id' => 20],
    'iam_model_has_permissions' => ['model_id' => null],
    'iam_model_has_roles'       => ['model_id' => 20],
    'iam_organization_members'  => ['user_id' => 50],

    'inventory_movements' => [
        'item_id'        => null,
        'location_id'    => null,
        'stock_id'       => null,
        'transaction_id' => null,
    ],
    'inventory_stocks' => ['item_id' => null, 'location_id' => null],

    'library_file_attachments' => ['attachable_id' => 250, 'file_id' => null],
    'library_files'            => ['folder_id' => null],
    'library_folders'          => [
        'parent_id'       => 50,
        'organization_id' => ['max' => 50, 'where' => 'parent_id IS NULL'],
    ],

    'master_addresses'     => ['addressable_id' => 20],
    'master_contacts'      => ['contactable_id' => 20],
    'master_labelables'    => ['labelable_id' => 250, 'label_id' => null],
    'master_note_mentions' => ['note_id' => 50],
    'master_notes'         => [
        'notable_id' => ['max' => 20, 'where' => 'parent_id IS NULL'],
        'parent_id'  => 1000,
    ],
    'master_units' => ['group_id' => 100],
];
