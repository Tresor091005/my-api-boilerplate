<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductCategory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'  => Product::factory(),
            'category_id' => Category::factory(),
        ];
    }
}
