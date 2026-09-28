<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Commitment\Enums\DeliverableState;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceDeliverable;

/** @extends Factory<ServiceDeliverable> */
final class ServiceDeliverableFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commitment = ServiceCommitment::factory()->create();

        return [
            'organization_id'  => $commitment->organization_id,
            'commitment_id'    => $commitment->id,
            'title'            => fake()->sentence(3),
            'execution_state'  => DeliverableState::Planned->value,
            'validation_state' => ReviewState::Unsubmitted->value,
        ];
    }
}
