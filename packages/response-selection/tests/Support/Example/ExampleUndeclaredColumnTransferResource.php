<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Renders its depot with ExampleUndeclaredColumnDepotResource, whose `label` misdeclares a column.
 * The action loads transfers whole, so a root field cannot hide such a gap: an included model is
 * narrowed to the columns its selection declares.
 */
final class ExampleUndeclaredColumnTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('source_depot', ExampleUndeclaredColumnDepotResource::class);
    }
}
