<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\ExternalIdentity;
use Lahatre\Iam\Models\User;

/** @extends Factory<ExternalIdentity> */
class ExternalIdentityFactory extends Factory
{
    protected $model = ExternalIdentity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'provider' => 'google',
            'issuer'  => 'https://accounts.google.com', 'subject' => fake()->unique()->numerify('#####################'),
        ];
    }
}
