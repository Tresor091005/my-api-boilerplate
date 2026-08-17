<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductCategory;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'product_id'      => Product::factory(['organization_id' => $organizationId]),
            'category_id'     => Category::factory(['organization_id' => $organizationId]),
        ];
    }
}
