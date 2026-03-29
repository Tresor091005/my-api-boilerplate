<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;
use Lahatre\Organization\Models\Organization;

class TestInventoryCompany extends Organization implements HasInventoryLocation
{
    use InteractsWithInventoryLocation;

    public function getMorphClass()
    {
        return (new Organization())->getMorphClass();
    }
}
