<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InventoryItemCollection extends BaseCollection
{
    public $collects = InventoryItemResource::class;
}
