<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class TagCollection extends BaseCollection
{
    public $collects = TagResource::class;
}
