<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    use ResolvesOrganizationId;

    public function forCatalogItem(CatalogItem $catalogItem): static
    {
        return $this->state([
            'id'              => $catalogItem->id,
            'organization_id' => $catalogItem->organization_id,
        ]);
    }

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            'product_id'      => Product::factory(['organization_id' => $organizationId]),
            // factory variants have no option values; a random signature lets several share a product
            'option_signature' => hash('sha256', (string) Str::uuid7()),
        ];
    }
}
