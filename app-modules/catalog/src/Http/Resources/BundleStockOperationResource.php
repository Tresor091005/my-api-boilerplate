<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class BundleStockOperationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('bundle_id')
            ->field('type')
            ->field('status')
            ->field('quantity')
            ->field('location_id')
            ->field('payload')
            ->field('composition_snapshot')
            ->field('out_transaction_id')
            ->field('in_transaction_id')
            ->field('completed_at')
            ->field('created_at')
            ->field('updated_at')
            ->include('bundle', BundleResource::class);
    }
}
