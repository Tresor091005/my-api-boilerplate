<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\Option\OptionInUseException;
use Lahatre\Catalog\Models\Option;

class OptionAssertion
{
    public function assertCanDelete(Option $option): void
    {
        if ($option->products()->exists()) {
            throw new OptionInUseException($option);
        }
    }
}
