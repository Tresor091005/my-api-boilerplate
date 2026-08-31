<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Models\Currency;
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
            'organization_id' => $organizationId,
            'movement_type'   => fake()->randomElement(MovementType::cases()),
            'transaction_id'  => InventoryTransaction::factory(['organization_id' => $organizationId]),
            'stock_id'        => InventoryStock::factory(['organization_id' => $organizationId]),
            'item_id'         => fn (array $attributes): string => (string) InventoryStock::query()
                ->findOrFail($attributes['stock_id'])
                ->item_id,
            'location_id' => fn (array $attributes): string => (string) InventoryStock::query()
                ->findOrFail($attributes['stock_id'])
                ->location_id,
            'quantity'       => $quantity,
            'base_unit_code' => fn (array $attributes): string => (string) InventoryStock::query()
                ->findOrFail($attributes['stock_id'])
                ->base_unit_code,
            'total_cost'              => $quantity * $unitCost,
            'currency_code'           => Currency::factory(),
            'expiration_date'         => null,
            'metadata'                => null,
            'exchange_metadata'       => null,
            'stock_metadata_snapshot' => null,
        ];
    }
}
