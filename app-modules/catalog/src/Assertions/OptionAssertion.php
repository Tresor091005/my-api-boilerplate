<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\OptionException;
use Lahatre\Catalog\Models\Option;

class OptionAssertion
{
    /**
     * Assert that an option can be deleted without invalidating products that use it.
     *
     * A focused existence query is sufficient because the deleting service does
     * not otherwise need to load the related products.
     *
     * @throws OptionException If at least one product still uses the option.
     */
    public function assertCanDelete(Option $option): void
    {
        if ($option->products()->exists()) {
            throw OptionException::inUse($option);
        }
    }
}
