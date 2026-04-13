<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Master\Models\Unit;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
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
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
            'unit_code'       => Unit::factory(),
            'step'            => 1,
            'is_active'       => true,
        ];
    }
}
