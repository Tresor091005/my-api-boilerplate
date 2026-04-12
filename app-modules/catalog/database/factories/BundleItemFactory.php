<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    public function definition(): array
    {
        $organizationId = getPermissionsTeamId() ?? Organization::factory();

        return [
            'organization_id' => $organizationId,
            'item_type'       => ProductVariant::class,
            'item_id'         => ProductVariant::factory(['organization_id' => $organizationId]),
            'bundle_id'       => Bundle::factory(['organization_id' => $organizationId]),
            'quantity'        => fake()->numberBetween(1, 10),
        ];
    }
}
