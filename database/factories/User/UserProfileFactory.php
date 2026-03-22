<?php

declare(strict_types=1);

namespace Database\Factories\User;

use App\Models\User\User;
use App\Models\User\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'bio'          => fake()->paragraph(),
            'cv_path'      => fake()->filePath(),
            'linkedin_url' => fake()->url(),
        ];
    }
}
