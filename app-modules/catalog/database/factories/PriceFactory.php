<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Price;
use Lahatre\Catalog\Models\Product;
use Lahatre\Master\Models\Currency;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
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
            'priceable_type'  => Product::class,
            'priceable_id'    => Product::factory(['organization_id' => $organizationId]),
            'currency_code'   => Currency::factory(),
            'min_quantity'    => 1,
            'max_quantity'    => null,
            'step'            => 1,
            'amount'          => fake()->numberBetween(100, 10000),
            'is_active'       => true,
            'active_from'     => null,
            'active_to'       => null,
        ];
    }
}
