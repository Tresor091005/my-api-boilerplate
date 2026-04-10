<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class InventorySummaryCollection extends BaseCollection
{
    public $collects = InventorySummaryRowResource::class;
}
