<?php

declare(strict_types=1);

return [
    'duplicate_option_names'                               => 'Option names must be unique within each product variant.',
    'duplicate_bundle_item'                                => 'A bundle item may only appear once.',
    'bundle_item_already_attached'                         => 'The item already belongs to this bundle.',
    'bundle_item_unavailable'                              => 'The selected item is unavailable.',
    'bundle_item_inactive'                                 => 'The selected item is inactive.',
    'bundle_item_type_mismatch'                            => 'The selected item type is invalid.',
    'bundle_item_unit_mismatch'                            => 'The selected unit does not belong to the item unit group.',
    'bundle_stock_operation_expiration_required'           => 'An expiration date is required for this expirable inventory item.',
    'bundle_stock_operation_expiration_prohibited'         => 'An expiration date is prohibited for this non-expirable inventory item.',
    'bundle_stock_operation_components_mismatch'           => 'The operation must provide exactly one entry for every bundle component.',
    'bundle_stock_operation_manual_stock_ids_required'     => 'Stock IDs are required when the deduction strategy is manual.',
    'bundle_stock_operation_stock_ids_prohibited'          => 'Stock IDs are not accepted for this operation direction.',
    'bundle_stock_operation_stock_ids_strategy_prohibited' => 'Stock IDs are only accepted when the deduction strategy is manual.',
];
