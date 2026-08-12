<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\OptionValueException;
use Lahatre\Catalog\Models\OptionValue;

class OptionValueAssertion
{
    public function assertCanDelete(OptionValue $optionValue): void
    {
        if ($optionValue->variants()->exists()) {
            throw OptionValueException::inUse($optionValue);
        }
    }
}
