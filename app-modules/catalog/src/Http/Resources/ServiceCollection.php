<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class ServiceCollection extends BaseCollection
{
    public $collects = ServiceResource::class;
}
