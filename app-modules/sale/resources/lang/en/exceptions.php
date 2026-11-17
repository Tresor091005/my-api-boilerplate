<?php

declare(strict_types=1);

return [
    'and_more'                                => ':list and :count more',
    'priced_line'                             => ':line at :level',
    'customer_unavailable'                    => 'The customer no longer exists. Choose another customer, or leave it out to sell to the walk-in customer.',
    'customer_inactive'                       => 'The customer :customer is inactive. Reactivate the customer, or sell to another one.',
    'price_level_imposed'                     => 'The customer :customer has their own price for these items: :items. Sell them at the customer\'s price level, or change the customer\'s price level.',
    'price_levels_unavailable'                => 'The price level of these items no longer exists: :items. Choose another level.',
    'price_level_outside_functional_currency' => 'The price level :level is in :currency, but sales are only calculated in :functional, the functional currency. Choose a level in :functional, or change the customer\'s price level.',
    'items_unavailable'                       => 'The items of these lines do not exist in the organization: :lines. Remove those lines from the sale.',
    'items_inactive'                          => 'These items are inactive: :items. Reactivate them, or remove them from the sale.',
    'services_not_sold'                       => 'Services cannot be sold yet: :items. Remove them from the sale.',
    'units_outside_item_group'                => 'These items are not sold in the unit given: :items. Choose a unit of the item\'s unit group.',
    'prices_missing'                          => 'These items have no price at the level given, in the unit given: :items. Set the price at that level, or choose another level or unit.',
    'prices_changed'                          => 'The price of these items changed since it was read: :items. Read the prices again, then send the draft.',
    'lines_duplicated'                        => 'These items are on several lines in the same unit: :items. Put each item and unit on one line.',
    'line_amount_too_large'                   => 'The amount of :item is too large: its price times its quantity exceeds 18 digits. Lower the quantity, or split the sale.',
    'total_too_large'                         => 'The total of the sale is too large. Split it into several sales.',
    'not_draft'                               => 'The sale :number is :status, and only a draft can be changed. Reopen it, then change it.',
    'version_changed'                         => 'The sale :number changed since it was read. Read it again, then send your changes.',
    'gesture_not_allowed'                     => 'The sale :number is :status, so :event is not possible. From :status, the possible gestures are: :events.',
    'gesture_not_allowed_final'               => 'The sale :number is :status, so :event is not possible. No gesture is possible on a :status sale.',
    'credit_to_walk_in_customer'              => 'A required payment share below 100 % is a credit, and a credit cannot be granted to the walk-in customer. Choose a named customer, or require 100 % payment.',
    'payment_due_date_missing'                => 'The sale :number is a credit, but no payment due date applies: it has none and its customer has none from today on. Type a due date on the sale, set one on the customer, or require 100 % payment.',
    'payment_due_date_passed'                 => 'The payment due date :date of the sale :number has passed. Type a due date from today on the draft.',
    'amounts_changed'                         => 'The amounts of these items changed since the sale :number was calculated: :items. Reopen the sale, read it again and validate it.',
    'prices_changed_since_approval'           => 'The catalog price of these items changed since the sale :number was approved: :items. Confirm with keep_approved_prices to sell at the approved prices, or reopen the sale to take the new ones.',
    'price_change'                            => ':line: :approved approved, :current now',
    'price_change_with_level'                 => ':line: :approved approved, :current now at :level, the customer\'s level',
    'confirmation_blocked'                    => ':reason The sale :number is approved and locked: to change it, reopen it first, then validate it again.',
];
