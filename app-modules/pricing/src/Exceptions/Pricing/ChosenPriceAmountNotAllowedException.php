<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Exceptions\Pricing;

use Lahatre\Shared\Exceptions\AssertionException;

class ChosenPriceAmountNotAllowedException extends AssertionException
{
    public function __construct(array $context = [])
    {
        parent::__construct(__('pricing::exceptions.chosen_amount_not_allowed'), $context);
    }
}
