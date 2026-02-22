<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use App\Http\Resources\BaseCollection;

class TagCollection extends BaseCollection
{
    public $collects = TagResource::class;
}
