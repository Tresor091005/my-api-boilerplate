<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\OptionValueException;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;

class OptionValueAssertion
{
    /**
     * Asserts that an option value belongs to the selected option and organization.
     *
     * @throws OptionValueException If the option value is attached to another option or organization.
     */
    public function assertBelongsToOption(Option $option, OptionValue $optionValue): void
    {
        if ((string) $optionValue->option_id !== (string) $option->getKey()
            || (string) $optionValue->organization_id !== (string) $option->organization_id) {
            throw OptionValueException::notAttachedToOption($option, $optionValue);
        }
    }

    /**
     * Assert that an option value can be deleted without invalidating variants that use it.
     *
     * A focused existence query is sufficient because the deleting service does
     * not otherwise need to load the related variants.
     *
     * @throws OptionValueException If at least one variant still uses the option value.
     */
    public function assertCanDelete(OptionValue $optionValue): void
    {
        if ($optionValue->variants()->exists()) {
            throw OptionValueException::inUse($optionValue);
        }
    }
}
