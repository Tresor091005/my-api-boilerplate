<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<ServiceDeliverableTemplate> */
class ServiceDeliverableTemplateFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $this->resolveOrganizationId(),
            'service_id'      => (string) Str::uuid7(),
            'name'            => fake()->words(3, true),
            'position'        => fake()->numberBetween(1, 10),
        ];
    }
}
