<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Option;
use Lahatre\Shared\Exceptions\AssertionException;

final class OptionException extends AssertionException
{
    public static function inUse(Option $option): self
    {
        return new self(
            __('catalog::exceptions.option_in_use'),
            ['option_id' => $option->id]
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
