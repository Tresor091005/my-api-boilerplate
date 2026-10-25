<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class ProductResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('handle')
            ->field('name')
            ->field('description')
            ->field('created_at')
            ->field('updated_at')
            // each option with the values the product's variants use: rendered only when asked for
            ->field('options', relations: ['optionValues.option'], computedBy: fn (Model $product): array => $product->getRelation('optionValues')
                ->groupBy('option_id')
                ->map(fn ($values): array => [
                    'name'   => $values->first()->option->name,
                    'values' => $values->pluck('value')->unique()->values()->all(),
                ])
                ->values()
                ->all(), default: false)
            ->include('variants', ProductVariantResource::class)
            ->include('categories', CategoryResource::class)
            ->include('main_files', ProductFileAttachmentResource::class, relation: 'mainFileAttachments')
            ->include('gallery_files', ProductFileAttachmentResource::class, relation: 'galleryFileAttachments');
    }
}
