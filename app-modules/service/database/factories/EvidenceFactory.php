<?php

declare(strict_types=1);

namespace Lahatre\Service\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Service\Enums\EvidenceStatus;
use Lahatre\Service\Models\Evidence;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<Evidence> */
class EvidenceFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'deliverable_id'  => (string) Str::uuid7(),
            'status'          => EvidenceStatus::Draft,
            'token'           => Str::uuid7(),
            'snapshot'        => ['name' => fake()->sentence()],
        ];
    }
}
