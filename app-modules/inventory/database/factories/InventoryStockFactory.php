<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 1000);
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'item_id'         => InventoryItem::factory(['organization_id' => $organizationId]),
            'location_id'     => InventoryLocation::factory(['organization_id' => $organizationId]),
            'unit_cost'       => fake()->numberBetween(100, 5000),
            'currency_code'   => Currency::factory()->create()->code,
            'quantity'        => $quantity,
            'remaining'       => $quantity,
            'unit_code'       => Unit::factory()->create()->code,
            'expiration_date' => null,
            'metadata'        => null,
        ];
    }
}
