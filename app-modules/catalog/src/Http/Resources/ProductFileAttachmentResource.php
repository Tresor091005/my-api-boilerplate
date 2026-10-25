<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Http\Resources\FileAttachmentApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A file attached to a product, with the product route serving its content.
 */
class ProductFileAttachmentResource extends FileAttachmentApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource->field('content_url', columns: ['attachable_id'], computedBy: fn (Model $attachment): string => route('lahatre.catalog.products.files.content', [
            'product'    => $attachment->getAttribute('attachable_id'),
            'attachment' => $attachment->getKey(),
        ], false));
    }
}
