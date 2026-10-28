<?php

declare(strict_types=1);

return [
    'plan' => [
        'in_use' => 'A plan on sale or with subscribers cannot be deleted.',
    ],
    'plan_version' => [
        'terms_required'            => 'A new plan needs a price, a currency and a billing interval first.',
        'price_without_currency'    => 'A price and its currency are given together.',
        'price_precision_exceeded'  => 'The price has more decimals than its currency allows.',
        'billing_interval_invalid'  => 'A billing interval must be a positive number of months.',
        'draft_missing'             => 'The plan has no draft.',
        'not_on_sale'               => 'The plan is not on sale.',
        'feature_disabled'          => 'A disabled feature cannot be sold in a plan.',
        'feature_type_changed'      => 'The feature is already granted with another type.',
        'negative_allowance'        => 'A feature allowance cannot be negative.',
        'boolean_feature_allowance' => 'A boolean feature cannot have an allowance.',
    ],
    'feature' => [
        'unavailable'        => 'This feature is not available for the organization.',
        'already_registered' => 'The feature is already registered.',
        'not_registered'     => 'The feature is not registered.',
        'invalid_key'        => 'A feature key must have the form module.feature.',
        'counter_missing'    => 'The counter of a capacity feature does not exist.',
        'not_capacity'       => 'Only a capacity feature counts its usage.',
        'negative_quantity'  => 'A capacity feature counter returned a negative quantity.',
    ],
];
