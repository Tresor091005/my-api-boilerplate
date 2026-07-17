<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $organizationId = getPermissionsTeamId() ?: (string) Str::uuid7();

        if (!getPermissionsTeamId()) {
            DB::table('organization_organizations')->insert([
                'id'         => $organizationId,
                'name'       => 'Factory Organization '.$organizationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'organization_id' => $organizationId,
            'movement_type'   => fake()->randomElement(MovementType::cases()),
            'transaction_id'  => InventoryTransaction::factory(['organization_id' => $organizationId]),
            'item_id'         => InventoryItem::factory(['organization_id' => $organizationId]),
            'stock_id'        => InventoryStock::factory(['organization_id' => $organizationId]),
            'location_id'     => InventoryLocation::factory(['organization_id' => $organizationId]),
            'quantity'        => fake()->numberBetween(1, 100),
            'unit_code'       => Unit::factory(),
            'unit_cost'       => fake()->numberBetween(100, 5000),
            'currency_code'   => Currency::factory(),
            'expiration_date' => null,
            'metadata'        => null,
        ];
    }
}
