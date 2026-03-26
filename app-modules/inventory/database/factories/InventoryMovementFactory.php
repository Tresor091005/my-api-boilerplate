<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'movement_type'   => fake()->randomElement(MovementType::cases()),
            'transaction_id'  => InventoryTransaction::factory(),
            'item_id'         => InventoryItem::factory(),
            'stock_id'        => InventoryStock::factory(),
            'location_id'     => InventoryLocation::factory(),
            'quantity'        => fake()->numberBetween(1, 100),
            'unit_code'       => Unit::factory(),
            'unit_cost'       => fake()->numberBetween(100, 5000),
            'currency_code'   => Currency::factory(),
            'expiration_date' => null,
            'metadata'        => null,
        ];
    }
}
