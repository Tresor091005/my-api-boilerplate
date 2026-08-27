<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InventoryStockCollection extends BaseCollection
{
    public $collects = InventoryStockResource::class;
}
