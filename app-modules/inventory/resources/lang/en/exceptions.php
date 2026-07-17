<?php

declare(strict_types=1);

return [
    'organization_context_required' => 'An organization context is required for Inventory operations.',
    'organization_mismatch'         => 'The referenced model does not belong to the current organization.',
    'idempotency_key_reused'        => 'The idempotency key has already been used with a different transaction payload.',
    'adjustment_no_op'              => 'The target quantity is already the current stock. Item :item_id, location :location_id.',
    'base_unit_not_found'           => 'System integrity error: base unit :base_unit_code for item :item_id could not be resolved.',
    'base_unit_ratio_integrity'     => 'System integrity error: base unit :base_unit_code for item :item_id must have a ratio of 1.',
    'insufficient_stock'            => 'Insufficient stock for item :item_id at location :location_id. Requested: :requested :unit_code, Available: :available :unit_code.',
    'transfer'                      => [
        'balance_mismatch'      => 'Transfer balance mismatch for item :item_id. Total IN: :in :base_unit, Total OUT: :out :base_unit.',
        'imbalance_destination' => 'Transfer imbalance detected for item :item: Destination location :location could not be fully filled from source stocks.',
        'imbalance_source'      => 'Transfer imbalance detected for item :item: Source stocks were not fully distributed to destinations.',
    ],
    'unit_group_mismatch' => 'Unit group mismatch for item :item_code: provided unit :provided_unit_code belongs to a different group than base unit :base_unit_code.',
];
