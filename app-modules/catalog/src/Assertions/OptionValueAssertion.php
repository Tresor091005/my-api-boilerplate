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

    public function assertCanDelete(OptionValue $optionValue): void
    {
        if ($optionValue->variants()->exists()) {
            throw OptionValueException::inUse($optionValue);
        }
    }
}
