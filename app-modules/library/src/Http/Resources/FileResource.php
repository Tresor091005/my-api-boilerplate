<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Http\Resources\Concerns\DescribesFile;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class FileResource extends ApiResource
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
            ->field('content_url', columns: ['id'], computedBy: fn (Model $file): string => route('lahatre.library.files.content', ['file' => $file->getKey()]))
            ->field('created_at')
            ->field('updated_at');
    }
}
