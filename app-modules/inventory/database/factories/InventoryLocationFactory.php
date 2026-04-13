<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_type' => 'test_warehouse',
            'external_id'   => (string) Str::uuid7(),
            'is_active'     => true,
        ];
    }
}
