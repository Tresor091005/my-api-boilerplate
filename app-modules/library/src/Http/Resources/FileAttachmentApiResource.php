<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Http\Resources\Concerns\DescribesFile;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * File attachment rendered through response selection: the attachment with its file's values beside
 * it, each selectable. Each owner extends it and adds `content_url`, since the content route belongs
 * to the owner.
 */
abstract class FileAttachmentApiResource extends ApiResource
{
    use DescribesFile;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('slot')
            ->field('position')
            ->field('file_id');

        foreach (self::fileValues() as $key => $value) {
            $resource->field($key, relations: ['file'], computedBy: fn (Model $attachment): mixed => $value($attachment->getRelation('file')));
        }
    }
}
