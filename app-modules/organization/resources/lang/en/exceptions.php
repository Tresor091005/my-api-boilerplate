<?php

declare(strict_types=1);

return [
    'context_required'                    => 'An active organization is required for this operation.',
    'functional_currency_immutable'       => 'The organization functional currency cannot be changed.',
    'exchange_rate_organization_mismatch' => 'The exchange rate does not belong to the active organization.',
    'currency_not_found'                  => 'The currency :code does not exist or is unavailable.',
    'currency_not_enabled'                => 'The currency :code is not enabled for this organization.',
    'functional_currency_must_be_enabled' => 'The organization functional currency must remain enabled.',
    'same_currency_pair'                  => 'The source and target currencies must be different.',
    'duplicate_exchange_rate'             => 'An exchange rate already exists for this currency pair and effective date.',
    'invalid_exchange_rate'               => 'The exchange rate must be a positive decimal value.',
    'effective_rate_immutable'            => 'An exchange rate that is already effective cannot be modified or deleted.',
    'exchange_rate_unavailable'           => 'No exchange rate is available from :source to :target for context :context and the requested date.',
];
