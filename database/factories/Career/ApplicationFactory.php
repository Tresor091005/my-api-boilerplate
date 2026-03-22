<?php

declare(strict_types=1);

namespace Database\Factories\Career;

use App\Models\Career\Application;
use App\Models\Career\Job;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'career_job_id' => Job::factory(),
            'user_id'       => User::factory(),
            'cover_letter'  => fake()->paragraph(),
            'status'        => fake()->randomElement(['pending', 'reviewed', 'shortlisted', 'rejected', 'accepted']),
        ];
    }
}
