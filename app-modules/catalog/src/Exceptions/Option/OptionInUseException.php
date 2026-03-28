<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\Option;

use Lahatre\Catalog\Models\Option;
use Lahatre\Shared\Exceptions\AssertionException;

class OptionInUseException extends AssertionException
{
    public function __construct(Option $option)
    {
        parent::__construct(
            __('catalog::exceptions.option_in_use'),
            ['option_id' => $option->id]
        );
    }
}
