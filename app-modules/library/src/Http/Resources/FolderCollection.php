<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class FolderCollection extends BaseCollection
{
    public $collects = FolderResource::class;
}
