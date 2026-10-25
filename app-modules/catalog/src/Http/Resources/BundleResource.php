<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\Catalog\Http\Resources\Concerns\DeclaresCatalogItemMembers;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class BundleResource extends ApiResource
{
    use DeclaresCatalogItemMembers;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('handle')
            ->field('name');
        self::catalogItemFields($resource);
        $resource
            ->field('created_at')
            ->field('updated_at')
            ->include('items', BundleItemResource::class);
        self::unitGroupInclude($resource);
        self::inventoryInclude($resource);
    }
}
