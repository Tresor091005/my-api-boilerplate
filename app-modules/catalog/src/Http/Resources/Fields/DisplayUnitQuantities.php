<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources\Fields;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\FieldComputer;

/**
 * Converts each row's quantity from base units to its display unit: bundle items and stock
 * transfer lines store both.
 */
final readonly class DisplayUnitQuantities implements FieldComputer
{
    public function __construct(private MasterInterface $master) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        return $models->mapWithKeys(fn (Model $item): array => [$item->getKey() => [
            'quantity' => (int) $this->master->convertUnitFromBase(
                (string) $item->getAttribute('quantity'),
                (string) $item->getAttribute('display_unit_code'),
            )['amount'],
        ]])->all();
    }
}
