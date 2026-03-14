<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use App\Http\Resources\BaseCollection;

class UnitCollection extends BaseCollection
{
    public $collects = UnitResource::class;
}
