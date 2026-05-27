<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Exceptions\Pricing;

use Lahatre\Shared\Exceptions\AssertionException;

class InvalidPricingPartyTargetException extends AssertionException
{
    public function __construct(array $context = [])
    {
        parent::__construct(__('pricing::exceptions.invalid_pricing_party_target'), $context);
    }
}
