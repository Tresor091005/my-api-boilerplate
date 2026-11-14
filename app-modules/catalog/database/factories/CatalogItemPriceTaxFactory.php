<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * Callers give catalog_item_price_id and a tax_id of the same organization.
 *
 * @extends Factory<CatalogItemPriceTax>
 */
class CatalogItemPriceTaxFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
        ];
    }
}
