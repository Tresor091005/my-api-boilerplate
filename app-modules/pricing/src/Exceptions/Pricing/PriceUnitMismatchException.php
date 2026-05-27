<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Exceptions\Pricing;

use Lahatre\Shared\Exceptions\AssertionException;

class PriceUnitMismatchException extends AssertionException
{
    public function __construct(array $context = [])
    {
        parent::__construct(__('pricing::exceptions.price_unit_mismatch'), $context);
    }
}
