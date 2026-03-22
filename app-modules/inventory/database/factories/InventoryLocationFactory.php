<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_type' => Company::class,
            'external_id'   => Company::factory(),
            'is_active'     => true,
        ];
    }
}
