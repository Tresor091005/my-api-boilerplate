<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A transfer whose depots render fixed views: `source_depot` its reference, `origin` its overview
 * with children. `siblings` reaches `source_depot` again one level down.
 */
final class ExampleSummaryTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('reference')
            ->field('status')
            ->include('source_depot', ExampleDepotResource::class, view: 'reference')
            ->include('lines', ExampleTransferLineResource::class)
            ->virtualInclude('origin', ExampleDepotResource::class, ExampleOriginDepots::class, columns: ['source_depot_id'], view: 'overview')
            ->virtualInclude('siblings', self::class, ExampleSiblingTransfers::class, asList: true, columns: ['source_depot_id'])
            ->view('compact', fields: 'reference', include: 'source_depot,lines(sku)');
    }
}
