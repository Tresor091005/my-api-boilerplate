<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
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
            'organization_id' => $organizationId,
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
        ];
    }
}
