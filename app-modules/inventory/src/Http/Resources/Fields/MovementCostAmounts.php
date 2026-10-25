<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources\Fields;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\FieldComputer;

/**
 * Shows a movement's total cost, stored in minor units, as a decimal amount of its currency.
 */
final readonly class MovementCostAmounts implements FieldComputer
{
    public function __construct(private MasterInterface $master) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        return $models->mapWithKeys(fn (Model $movement): array => [
            $movement->getKey() => [
                'total_cost' => InventoryValues::amount($this->master, $movement, (int) $movement->getAttribute('total_cost')),
            ],
        ])->all();
    }
}
