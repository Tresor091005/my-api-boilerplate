<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Lahatre\Library\Http\Resources\Concerns\DescribesFile;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A file in the trash: no content URL, since its content is not served until it is restored.
 */
class TrashFileResource extends ApiResource
{
    use DescribesFile;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('folder_id');

        self::defineFileValues($resource);

        $resource
            ->field('uploaded_by')
            ->field('created_at')
            ->field('updated_at')
            ->field('deleted_at');
    }
}
