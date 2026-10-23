<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Wrong on purpose: `line_count` counts with a query per transfer inside a closure, so the number of
 * queries grows with the number of rows. ExampleTransferTotals computes it for the whole node.
 */
final class ExampleQueryingClosureTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('line_count', columns: [], computedBy: fn (Model $transfer): int => ExampleTransferLine::query()->where('transfer_id', $transfer->getKey())->count());
    }
}
