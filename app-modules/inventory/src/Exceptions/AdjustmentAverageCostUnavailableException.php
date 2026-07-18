<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class AdjustmentAverageCostUnavailableException extends AssertionException
{
    public static function currencyRequired(string $itemId, string $locationId): self
    {
        return new self(
            __('inventory::exceptions.adjustment_currency_required', [
                'item_id'     => $itemId,
                'location_id' => $locationId,
            ]),
            [
                'item_id'     => $itemId,
                'location_id' => $locationId,
                'reason'      => 'currency_required',
            ]
        );
    }

    public static function stockUnavailable(string $itemId, string $locationId, string $currencyCode): self
    {
        return new self(
            __('inventory::exceptions.adjustment_average_cost_unavailable', [
                'item_id'       => $itemId,
                'location_id'   => $locationId,
                'currency_code' => $currencyCode,
            ]),
            [
                'item_id'       => $itemId,
                'location_id'   => $locationId,
                'currency_code' => $currencyCode,
                'reason'        => 'stock_unavailable',
            ]
        );
    }

    private function __construct(string $message, array $context)
    {
        parent::__construct($message, $context);
    }
}
