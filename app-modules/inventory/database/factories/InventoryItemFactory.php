<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Inventory\Models\InventoryItem;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'itemable_type'      => ProductVariant::class,
            'itemable_id'        => ProductVariant::factory(),
            'sku'                => fake()->unique()->bothify('SKU-####-????'),
            'base_unit_code'     => 'unit',
            'deduction_strategy' => null,
            'is_active'          => true,
        ];
    }
}
