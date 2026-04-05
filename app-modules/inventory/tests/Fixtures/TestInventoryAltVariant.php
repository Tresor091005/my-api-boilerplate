<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Illuminate\Support\Str;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;

class TestInventoryAltVariant extends ProductVariant implements HasInventoryItem
{
    use InteractsWithInventoryItem;

    public function getMorphClass()
    {
        return 'test_inventory_alt_variant';
    }

    public function getUnitGroupId(): string
    {
        return (string) $this->unit_group_id;
    }

    public function getSku(): string
    {
        return (string) Str::upper(Str::random(10));
    }
}
