<?php

declare(strict_types=1);

namespace Lahatre\Service\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Service\Models\ServiceCommitment;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<ServiceCommitment> */
class ServiceCommitmentFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'service_id'      => (string) Str::uuid7(),
            'sale_line_id'    => (string) Str::uuid7(),
            'status'          => CommitmentStatus::Draft,
        ];
    }
}
