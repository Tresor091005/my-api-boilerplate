<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\VirtualIncludeLoader;

/**
 * The other transfers leaving the same depot as each transfer of a node, in one query.
 */
final class ExampleSiblingTransfers implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        $byDepot = ExampleTransfer::query()
            ->whereIn('source_depot_id', $parents->pluck('source_depot_id')->unique()->all())
            ->orderBy('id')
            ->get()
            ->groupBy('source_depot_id');

        return $parents->mapWithKeys(fn (Model $transfer): array => [
            $transfer->getKey() => $byDepot->get($transfer->getAttribute('source_depot_id'), collect())
                ->reject(fn (Model $sibling): bool => $sibling->is($transfer))
                ->values()
                ->all(),
        ])->all();
    }
}
