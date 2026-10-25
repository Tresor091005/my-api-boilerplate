<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Http\Resources\FileAttachmentApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A file attached to a service, with the service route serving its content.
 */
class ServiceFileAttachmentResource extends FileAttachmentApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource->field('content_url', columns: ['attachable_id'], computedBy: fn (Model $attachment): string => route('lahatre.catalog.services.files.content', [
            'service'    => $attachment->getAttribute('attachable_id'),
            'attachment' => $attachment->getKey(),
        ], false));
    }
}
