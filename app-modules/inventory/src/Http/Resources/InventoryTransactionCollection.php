<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InventoryTransactionCollection extends BaseCollection
{
    public $collects = InventoryTransactionResource::class;
}
