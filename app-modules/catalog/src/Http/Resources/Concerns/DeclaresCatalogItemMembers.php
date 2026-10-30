<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Http\Resources\InventoryItemResource;
use Lahatre\Master\Http\Resources\UnitGroupResource;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\Tax\Http\Resources\TaxProfileResource;

/**
 * Members a sellable reads from its catalog item: variants, bundles and services share them.
 */
trait DeclaresCatalogItemMembers
{
    protected static function catalogItemFields(ResourceDefinition $resource): void
    {
        $resource
            ->field('sku', relations: ['catalogItem'], computedBy: fn (Model $item): ?string => $item->getRelation('catalogItem')?->getAttribute('sku'))
            ->field('unit_group_id', relations: ['catalogItem'], computedBy: fn (Model $item): ?string => $item->getRelation('catalogItem')?->getAttribute('unit_group_id'))
            ->field('tax_profile_id', relations: ['catalogItem'], computedBy: fn (Model $item): ?string => $item->getRelation('catalogItem')?->getAttribute('tax_profile_id'))
            ->field('is_active', relations: ['catalogItem'], computedBy: fn (Model $item): ?bool => $item->getRelation('catalogItem')?->getAttribute('is_active'));
    }

    protected static function unitGroupInclude(ResourceDefinition $resource): void
    {
        $resource->virtualInclude(
            'unit_group',
            UnitGroupResource::class,
            fn (Model $item): ?Model => $item->getRelation('catalogItem')?->getRelation('unitGroup'),
            relations: ['catalogItem.unitGroup'],
        );
    }

    protected static function taxProfileInclude(ResourceDefinition $resource): void
    {
        $resource->virtualInclude(
            'tax_profile',
            TaxProfileResource::class,
            fn (Model $item): ?Model => $item->getRelation('catalogItem')?->getRelation('taxProfile'),
            relations: ['catalogItem.taxProfile'],
        );
    }

    protected static function inventoryInclude(ResourceDefinition $resource): void
    {
        $resource->virtualInclude(
            'inventory',
            InventoryItemResource::class,
            fn (Model $item): ?Model => $item->getRelation('catalogItem')?->getRelation('inventoryItem'),
            relations: ['catalogItem.inventoryItem'],
        );
    }
}
