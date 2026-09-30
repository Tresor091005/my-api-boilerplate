<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Organization\Models\Organization;

/** @extends Factory<Invitation> */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email'           => fake()->unique()->safeEmail(),
            'token_hash'      => hash('sha256', Str::random(64)),
            'expires_at'      => now()->addDays(7),
        ];
    }
}
