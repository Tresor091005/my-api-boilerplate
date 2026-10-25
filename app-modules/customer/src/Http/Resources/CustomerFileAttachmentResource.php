<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Http\Resources\FileAttachmentApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A file attached to a customer, with the customer route serving its content.
 */
class CustomerFileAttachmentResource extends FileAttachmentApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource->field('content_url', columns: ['attachable_id'], computedBy: fn (Model $attachment): string => route('lahatre.customer.customers.files.content', [
            'customer'   => $attachment->getAttribute('attachable_id'),
            'attachment' => $attachment->getKey(),
        ], false));
    }
}
