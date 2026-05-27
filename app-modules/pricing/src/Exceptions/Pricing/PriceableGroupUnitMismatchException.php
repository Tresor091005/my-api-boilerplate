<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Exceptions\Pricing;

use Lahatre\Shared\Exceptions\AssertionException;

class PriceableGroupUnitMismatchException extends AssertionException
{
    public function __construct(array $context = [])
    {
        parent::__construct(__('pricing::exceptions.priceable_group_unit_mismatch'), $context);
    }
}
