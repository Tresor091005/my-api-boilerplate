<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources\Fields;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;

/**
 * Display values shared by the stock and movement Resources and the legacy lot Resource, so each
 * value is computed in one place.
 */
final class InventoryValues
{
    /**
     * A stock cost in minor units: `total_cost` is what the remaining quantity is worth.
     */
    public static function stockMinorCost(Model $stock, string $field): int
    {
        return match ($field) {
            'total_cost' => ((int) $stock->getAttribute('remaining') * (int) $stock->getAttribute('unit_cost'))
                + (int) $stock->getAttribute('cost_remainder'),
            default => (int) $stock->getAttribute($field),
        };
    }

    /**
     * A minor amount as a decimal of the row's currency, or the raw integer without currency.
     */
    public static function amount(MasterInterface $master, Model $model, int $minorAmount): string|int
    {
        $currencyCode = $model->getAttribute('currency_code');

        if (!$currencyCode) {
            return $minorAmount;
        }

        return $master->fromMinor((string) $minorAmount, $currencyCode);
    }

    public static function expirationDate(Model $model): ?string
    {
        $expirationDate = $model->getAttribute('expiration_date');

        return $expirationDate instanceof CarbonInterface ? $expirationDate->toDateString() : null;
    }

    public static function daysRemaining(Model $model): ?int
    {
        $expirationDate = $model->getAttribute('expiration_date');

        if (!$expirationDate instanceof CarbonInterface) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expirationDate->startOfDay(), false);
    }
}
