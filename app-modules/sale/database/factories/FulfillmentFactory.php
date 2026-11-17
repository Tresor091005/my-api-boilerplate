<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\Sale;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Fulfillment>
 */
class FulfillmentFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'sale_id'         => fn (array $attributes): string => Sale::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'number'          => fake()->unique()->bothify('FL-####-######'),
            'status'          => FulfillmentStatus::Draft,
        ];
    }
}
