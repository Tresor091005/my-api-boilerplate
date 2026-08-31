<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            'item_type'       => CatalogItemType::ProductVariant,
            'sku'             => fake()->unique()->bothify('SKU-####-????'),
            'unit_group_id'   => UnitGroup::factory(),
            'is_stockable'    => true,
            'is_active'       => true,
        ];
    }
}
