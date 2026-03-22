<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;

/**
 * @extends Factory<VariantOptionValue>
 */
class VariantOptionValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'      => Product::factory(),
            'variant_id'      => ProductVariant::factory(),
            'option_id'       => Option::factory(),
            'option_value_id' => OptionValue::factory(),
        ];
    }
}
