<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\Concerns\DeclaresCatalogItemMembers;
use Lahatre\Master\Http\Resources\LabelResource;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class ProductVariantResource extends ApiResource
{
    use DeclaresCatalogItemMembers;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            // the product name followed by the variant's option values
            ->field('name', columns: ['product_id'], relations: ['product', 'optionValues.option'])
            ->field('product_id');
        self::catalogItemFields($resource);
        $resource
            ->field('created_at')
            ->field('updated_at')
            ->field('options', relations: ['optionValues.option'], computedBy: fn (Model $variant): array => $variant->getRelation('optionValues')
                ->values()
                ->mapWithKeys(fn ($optionValue): array => [$optionValue->option->name => $optionValue->value])
                ->all())
            ->include('labels', LabelResource::class);
        self::unitGroupInclude($resource);
        self::taxProfileInclude($resource);
        self::inventoryInclude($resource);
    }
}
