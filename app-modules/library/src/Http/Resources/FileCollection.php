<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class FileCollection extends BaseCollection
{
    public $collects = FileResource::class;
}
