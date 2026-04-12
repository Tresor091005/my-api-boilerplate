<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $organizationId = getPermissionsTeamId() ?? Organization::factory();

        return [
            'organization_id'     => $organizationId,
            'product_id'          => Product::factory(['organization_id' => $organizationId]),
            'sku'                 => fake()->unique()->bothify('SKU-####-????'),
            'unit_group_id'       => UnitGroup::factory(),
            'should_manage_stock' => true,
            'is_active'           => true,
        ];
    }
}
