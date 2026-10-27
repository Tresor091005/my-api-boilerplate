<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\PlatformUser;
use Lahatre\Iam\Models\User;

/** @extends Factory<PlatformUser> */
class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

    public function definition(): array
    {
        return ['user_id' => User::factory()];
    }
}
