<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources\Fields;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\FieldComputer;

/**
 * Shows a stock's costs, stored in minor units, as decimal amounts of its currency.
 */
final readonly class StockCostAmounts implements FieldComputer
{
    public function __construct(private MasterInterface $master) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        return $models->mapWithKeys(fn (Model $stock): array => [
            $stock->getKey() => collect($fields)
                ->mapWithKeys(fn (string $field): array => [
                    $field => InventoryValues::amount($this->master, $stock, InventoryValues::stockMinorCost($stock, $field)),
                ])
                ->all(),
        ])->all();
    }
}
