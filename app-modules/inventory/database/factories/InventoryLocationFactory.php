<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
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
            'external_type'   => 'test_warehouse',
            'external_id'     => (string) Str::uuid7(),
            'is_active'       => true,
        ];
    }
}
