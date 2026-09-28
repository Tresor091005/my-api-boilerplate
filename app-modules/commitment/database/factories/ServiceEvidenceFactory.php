<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Models\ServiceEvidence;

/** @extends Factory<ServiceEvidence> */
final class ServiceEvidenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $unit = ServiceDeliverable::factory()->create();

        return [
            'organization_id' => $unit->organization_id,
            'commitment_id'   => $unit->commitment_id,
            'deliverable_id'  => $unit->id,
            'version'         => 1,
            'title'           => $unit->title,
            'description'     => $unit->description,
            'outcome'         => 'performed',
            'narrative'       => fake()->sentence(),
            'submitted_at'    => now(),
        ];
    }
}
