<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class StockLocationException extends AssertionException
{
    public static function managedBySystem(string $stockLocationId): self
    {
        return new self(
            __('catalog::exceptions.stock_location_managed_by_system'),
            ['stock_location_id' => $stockLocationId],
        );
    }
}
