<?php

declare(strict_types=1);

return [
    'plan' => [
        'in_use'          => 'A plan on sale, with subscribers, or with a payment in progress cannot be deleted.',
        'trial_plan_kept' => 'The trial plan cannot be deleted; archive it to stop giving trials.',
    ],
    'plan_version' => [
        'terms_required'            => 'A new plan needs a price, a currency and a billing interval first.',
        'price_without_currency'    => 'A price and its currency are given together.',
        'trial_plan_not_free'       => 'The trial plan is free; its price stays 0.',
        'price_precision_exceeded'  => 'The price has more decimals than its currency allows.',
        'billing_interval_invalid'  => 'A billing interval must be from 1 to :max months.',
        'draft_missing'             => 'The plan has no draft.',
        'not_on_sale'               => 'The plan is not on sale.',
        'feature_disabled'          => 'A disabled feature cannot be sold in a plan.',
        'feature_type_changed'      => 'The feature is already granted with another type.',
        'negative_allowance'        => 'A feature allowance cannot be negative.',
        'boolean_feature_allowance' => 'A boolean feature cannot have an allowance.',
    ],
    'subscription' => [
        'already_subscribed'                  => 'The organization already has a subscription; renew it or change its plan.',
        'not_subscribed'                      => 'The organization has no subscription to renew or change; subscribe first.',
        'plan_not_on_sale'                    => 'This plan is not on sale.',
        'invalid_periods'                     => 'A payment covers at least one period.',
        'advance_limit_exceeded'              => 'A subscription can be paid at most :months months ahead.',
        'next_plan_already_chosen'            => 'Another plan is already chosen for the next period.',
        'current_plan_paid_in_advance'        => 'The current plan is already paid in advance; another plan can be chosen for after during its last paid period.',
        'change_requires_active_subscription' => 'Only an active subscription can change its plan now; renew it instead.',
        'change_to_same_plan'                 => 'The subscription already has this plan; renew it instead.',
        'currency_mismatch'                   => 'The new plan is sold in another currency.',
        'remaining_value_covers_price'        => 'What remains is worth as much as this plan or more; choose it for the next period instead.',
    ],
    'checkout' => [
        'cancelled' => 'This payment was replaced by a newer one.',
        'expired'   => 'This payment has expired; start a new one.',
        'outdated'  => 'The subscription changed since this payment started.',
    ],
    'feature' => [
        'already_registered' => 'The feature is already registered.',
        'not_registered'     => 'The feature is not registered.',
        'invalid_key'        => 'A feature key must have the form module.feature.',
        'counter_missing'    => 'The counter of a capacity feature does not exist.',
        'not_capacity'       => 'Only a capacity feature counts its usage.',
        'negative_quantity'  => 'A capacity feature counter returned a negative quantity.',
    ],
    'subscription_required' => [
        'no_subscription' => 'The organization has no subscription; subscribe to a plan to continue.',
        'suspended'       => 'The subscription is suspended; subscribe to a plan to continue.',
    ],
    'feature_unavailable' => [
        'disabled'        => 'This feature is currently unavailable.',
        'no_subscription' => 'The organization has no subscription; subscribe to a plan to use this feature.',
        'suspended'       => 'The subscription is suspended; subscribe to a plan to use this feature again.',
        'not_in_plan'     => 'The current plan does not include this feature.',
        'limit_reached'   => 'The current plan allows at most :allowance; this limit is reached.',
    ],
];
