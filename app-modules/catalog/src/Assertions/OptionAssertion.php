<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\OptionException;
use Lahatre\Catalog\Models\Option;

class OptionAssertion
{
    public function assertCanDelete(Option $option): void
    {
        if ($option->products()->exists()) {
            throw OptionException::inUse($option);
        }
    }
}
