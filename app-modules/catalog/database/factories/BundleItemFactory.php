<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    use ResolvesOrganizationId;

    public function forProductVariant(ProductVariant $productVariant, Unit $unit): static
    {
        return $this->state([
            'organization_id'   => $productVariant->organization_id,
            'item_type'         => CatalogItemType::ProductVariant->value,
            'item_id'           => $productVariant->id,
            'display_unit_code' => $unit->code,
        ]);
    }

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id'   => $organizationId,
            'item_type'         => CatalogItemType::ProductVariant->value,
            'item_id'           => ProductVariant::factory(['organization_id' => $organizationId]),
            'bundle_id'         => Bundle::factory(['organization_id' => $organizationId]),
            'quantity'          => fake()->numberBetween(1, 10),
            'display_unit_code' => Unit::factory(),
        ];
    }
}
