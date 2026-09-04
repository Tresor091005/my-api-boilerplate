<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $this->resolveOrganizationId(),
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
        ];
    }

    public function forCatalogItem(CatalogItem $catalogItem): static
    {
        return $this->state([
            'id'              => $catalogItem->id,
            'organization_id' => $catalogItem->organization_id,
        ]);
    }
}
