<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_type' => Organization::class,
            'external_id'   => Organization::factory(),
            'is_active'     => true,
        ];
    }
}
