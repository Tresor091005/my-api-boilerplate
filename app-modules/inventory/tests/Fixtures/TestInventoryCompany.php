<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use App\Models\Company\Company;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;

class TestInventoryCompany extends Company implements HasInventoryLocation
{
    use InteractsWithInventoryLocation;

    public function getMorphClass()
    {
        return (new Company())->getMorphClass();
    }
}
