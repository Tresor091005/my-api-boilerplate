<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Pricing\Models\PriceableGroup;

/**
 * @extends Factory<PriceableGroup>
 */
class PriceableGroupFactory extends Factory
{
    public function definition(): array
    {
        $organizationId = getPermissionsTeamId() ?: (string) Str::uuid7();

        if (!getPermissionsTeamId()) {
            DB::table('organization_organizations')->insert([
                'id'         => $organizationId,
                'name'       => 'Pricing Priceable Group Organization '.$organizationId,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        return [
            'organization_id' => $organizationId,
            'name'            => fake()->unique()->words(2, true),
            'description'     => fake()->sentence(),
            'is_active'       => true,
            'metadata'        => null,
        ];
    }
}
