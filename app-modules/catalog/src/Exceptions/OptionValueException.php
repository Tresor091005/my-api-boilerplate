<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Shared\Exceptions\AssertionException;

final class OptionValueException extends AssertionException
{
    public static function inUse(OptionValue $optionValue): self
    {
        return new self(
            __('catalog::exceptions.option_value_in_use'),
            ['option_value_id' => $optionValue->id]
        );
    }

    public static function notAttachedToOption(Option $option, OptionValue $optionValue): self
    {
        return new self(
            __('catalog::exceptions.option_value_not_attached_to_option'),
            [
                'option_id'       => $option->id,
                'option_value_id' => $optionValue->id,
            ]
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
