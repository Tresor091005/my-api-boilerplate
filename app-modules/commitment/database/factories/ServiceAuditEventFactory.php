<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Commitment\Models\ServiceAuditEvent;
use Lahatre\Commitment\Models\ServiceCommitment;

/** @extends Factory<ServiceAuditEvent> */
final class ServiceAuditEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commitment = ServiceCommitment::factory()->create();

        return [
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'event_type'      => 'created',
            'actor_type'      => 'provider',
            'created_at'      => now(),
        ];
    }
}
