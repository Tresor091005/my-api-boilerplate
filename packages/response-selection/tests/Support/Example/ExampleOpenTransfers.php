<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Collection;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\VirtualIncludeLoader;

/**
 * Open transfers leaving each depot of a node, in one query.
 */
final class ExampleOpenTransfers implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        return ExampleTransfer::query()
            ->whereIn('source_depot_id', $parents->modelKeys())
            ->where('status', 'open')
            ->orderBy('id')
            ->get()
            ->groupBy('source_depot_id')
            ->all();
    }
}
