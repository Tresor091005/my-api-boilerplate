<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\ProductVariant;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_type' => ProductVariant::class,
            'item_id'   => ProductVariant::factory(),
            'bundle_id' => Bundle::factory(),
            'quantity'  => fake()->numberBetween(1, 10),
        ];
    }
}
