<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * Callers give catalog_item_id, price_level_id and a unit_id of the item's unit group.
 *
 * @extends Factory<CatalogItemPrice>
 */
class CatalogItemPriceFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'amount'          => fake()->numberBetween(100, 100_000),
        ];
    }
}
