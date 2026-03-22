<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 1000);

        return [
            'item_id'         => InventoryItem::factory(),
            'location_id'     => InventoryLocation::factory(),
            'unit_cost'       => fake()->numberBetween(100, 5000),
            'currency_code'   => Currency::factory(),
            'quantity'        => $quantity,
            'remaining'       => $quantity,
            'unit_code'       => Unit::factory(),
            'peremption_date' => null,
            'metadata'        => null,
        ];
    }
}
