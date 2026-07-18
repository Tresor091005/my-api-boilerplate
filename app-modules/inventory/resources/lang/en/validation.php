<?php

declare(strict_types=1);

return [
    'duplicate_stock_ids'                    => 'The same stock ID cannot be selected more than once in the same movement.',
    'adjustment_duplicate_item_location'     => 'For Adjustment transactions, the same item cannot appear multiple times for the same location.',
    'in_transaction_only_in_movements'       => "An 'IN' transaction can only contain 'in' movements.",
    'out_transaction_only_out_movements'     => "An 'OUT' transaction can only contain 'out' movements.",
    'transfer_requires_in_and_out_movements' => "A 'TRANSFER' transaction must have at least one 'in' and one 'out' movement.",
    'item_invalid_or_inactive'               => 'The selected item is invalid or inactive.',
    'location_invalid_or_inactive'           => 'The selected location is invalid or inactive.',
    'unit_code_invalid'                      => 'The selected unit code is invalid.',
    'currency_code_invalid'                  => 'The selected currency code is invalid.',
    'in_total_cost_required'                 => "The total cost is required for 'in' movements in an 'IN' transaction.",
    'in_currency_code_required'              => "The currency code is required for 'in' movements in an 'IN' transaction.",
    'transfer_in_total_cost_prohibited'      => "The total cost is prohibited for 'in' movements in a 'TRANSFER' transaction (it is inherited from the source).",
    'transfer_in_currency_code_prohibited'   => "The currency code is prohibited for 'in' movements in a 'TRANSFER' transaction.",
    'transfer_in_expiration_date_prohibited' => "The expiration date is prohibited for 'in' movements in a 'TRANSFER' transaction.",
    'in_strategy_prohibited'                 => "Stock deduction strategy is prohibited for 'in' movements.",
    'in_stock_ids_prohibited'                => "Stock IDs are prohibited for 'in' movements.",
    'stock_metadata_in_only'                 => "Stock metadata is only allowed for regular 'IN' transactions.",
    'out_stock_metadata_prohibited'          => "Stock metadata is prohibited for 'out' movements.",
    'out_total_cost_prohibited'              => "The total cost is prohibited for 'out' movements.",
    'out_currency_code_prohibited'           => "The currency code is prohibited for 'out' movements.",
    'out_expiration_date_prohibited'         => "The expiration date is prohibited for 'out' movements.",
    'total_cost_precision'                   => 'The total cost for currency :currency_code must have at most :precision decimal places.',
    'manual_stock_ids_required'              => 'Stock IDs are required when strategy is manual.',
    'stock_id_invalid'                       => 'Stock ID :stock_id is invalid.',
    'stock_id_wrong_scope'                   => 'Stock ID :stock_id does not belong to the correct item and location.',
    'transaction_single_currency'            => 'All movements in a transaction must use the same currency code.',
    'unit_group_mismatch'                    => 'Unit :unit_code belongs to a different group than item base unit :base_unit_code.',
    'transfer_imbalance'                     => 'Transfer imbalance for item :item_id. Total IN: :total_in, Total OUT: :total_out (in base unit :base_unit_code).',
];
