<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

final class ExampleTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('reference')
            ->field('status')
            ->field('label', columns: ['reference', 'status'], computedBy: fn (Model $transfer): string => "{$transfer->getAttribute('reference')} ({$transfer->getAttribute('status')})")
            ->field('line_count', computedBy: ExampleTransferTotals::class)
            ->field('total_quantity', computedBy: ExampleTransferTotals::class)
            ->include('source_depot', ExampleDepotResource::class)
            ->include('lines', ExampleTransferLineResource::class);
    }
}
