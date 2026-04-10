<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InventoryLocationCollection extends BaseCollection
{
    public $collects = InventoryLocationResource::class;
}
