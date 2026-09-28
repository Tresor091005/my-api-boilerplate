<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Commitment\Models\ServiceEvidence;
use Lahatre\Commitment\Models\ServiceReview;

/** @extends Factory<ServiceReview> */
final class ServiceReviewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $evidence = ServiceEvidence::factory()->create();

        return [
            'organization_id' => $evidence->organization_id,
            'commitment_id'   => $evidence->commitment_id,
            'subject_type'    => 'evidence',
            'subject_id'      => $evidence->id,
            'decision'        => 'accept',
            'actor_email'     => fake()->safeEmail(),
        ];
    }
}
