<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;

/** @extends Factory<ServiceCommitmentProposal> */
final class ServiceCommitmentProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commitment = ServiceCommitment::factory()->create();

        return [
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'version'         => 1,
            'title'           => fake()->sentence(3),
            'terms'           => null,
            'state'           => 'draft',
        ];
    }
}
