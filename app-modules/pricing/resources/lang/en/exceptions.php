<?php

declare(strict_types=1);

return [
    'invalid_priceable_target'       => 'The selected priceable target is not eligible for pricing.',
    'invalid_pricing_party_target'   => 'The selected pricing party target is not eligible for pricing.',
    'price_scope_conflict'           => 'An active price entry already exists for the same pricing scope.',
    'price_range_invalid'            => 'The provided pricing range is invalid.',
    'price_unit_mismatch'            => 'The selected unit code is not compatible with the targeted priceable scope.',
    'priceable_group_unit_mismatch'  => 'All members of a pricing priceable group must share the same unit group.',
    'chosen_amount_not_allowed'      => 'The chosen amount is not allowed for the current pricing context.',
    'pricing_bypass_reason_required' => 'A pricing bypass reason is required when the chosen amount is not applicable.',
];
