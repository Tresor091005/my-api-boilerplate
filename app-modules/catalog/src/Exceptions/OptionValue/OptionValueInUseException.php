<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\OptionValue;

use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Shared\Exceptions\AssertionException;

class OptionValueInUseException extends AssertionException
{
    public function __construct(OptionValue $optionValue)
    {
        parent::__construct(
            __('catalog::exceptions.option_value_in_use'),
            ['option_value_id' => $optionValue->id]
        );
    }
}
