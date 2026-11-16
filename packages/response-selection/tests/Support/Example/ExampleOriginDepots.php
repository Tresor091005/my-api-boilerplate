<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\VirtualIncludeLoader;

/**
 * The depot each transfer of a node leaves from, in one query.
 */
final class ExampleOriginDepots implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        $depots = ExampleDepot::query()->whereIn('id', $parents->pluck('source_depot_id')->unique()->all())->get()->keyBy('id');

        return $parents->mapWithKeys(fn (Model $transfer): array => [$transfer->getKey() => $depots->get($transfer->getAttribute('source_depot_id'))])->all();
    }
}
