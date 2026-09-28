<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Lahatre\Commitment\Models\GuestAccessChallenge;
use Lahatre\Commitment\Models\ServiceCommitment;

/** @extends Factory<GuestAccessChallenge> */
final class GuestAccessChallengeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commitment = ServiceCommitment::factory()->create();

        return [
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'code_hash'       => Hash::make('123456'),
            'attempts'        => 0,
            'expires_at'      => now()->addMinutes(10),
        ];
    }
}
