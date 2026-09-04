<?php

declare(strict_types=1);

namespace Lahatre\Service\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Service\Models\ServiceDeliverable;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<ServiceDeliverable> */
class ServiceDeliverableFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'commitment_id'   => (string) Str::uuid7(),
            'name'            => fake()->words(3, true),
            'position'        => fake()->numberBetween(1, 10),
            'status'          => DeliverableStatus::Pending,
        ];
    }
}
