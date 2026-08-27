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
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->numberBetween(100, 5000);

        return [
            'organization_id'         => $organizationId,
            'movement_type'           => fake()->randomElement(MovementType::cases()),
            'transaction_id'          => InventoryTransaction::factory(['organization_id' => $organizationId]),
            'item_id'                 => InventoryItem::factory(['organization_id' => $organizationId]),
            'stock_id'                => InventoryStock::factory(['organization_id' => $organizationId]),
            'location_id'             => InventoryLocation::factory(['organization_id' => $organizationId]),
            'quantity'                => $quantity,
            'unit_code'               => Unit::factory(),
            'total_cost'              => $quantity * $unitCost,
            'currency_code'           => Currency::factory(),
            'expiration_date'         => null,
            'metadata'                => null,
            'exchange_metadata'       => null,
            'stock_metadata_snapshot' => null,
        ];
    }
}
