<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Commitment\Models\GuestAccessSession;
use Lahatre\Commitment\Models\ServiceCommitment;

/** @extends Factory<GuestAccessSession> */
final class GuestAccessSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commitment = ServiceCommitment::factory()->create();

        return [
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'token_hash'      => hash('sha256', Str::random(64)),
            'expires_at'      => now()->addHour(),
        ];
    }
}
