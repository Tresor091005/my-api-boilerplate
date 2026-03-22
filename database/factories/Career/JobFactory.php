<?php

declare(strict_types=1);

namespace Database\Factories\Career;

use App\Models\Career\Job;
use App\Models\Company\Company;
use App\Models\Company\CompanyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id'   => Company::factory(),
            'posted_by'    => CompanyMember::factory(),
            'title'        => fake()->jobTitle(),
            'description'  => fake()->paragraphs(3, true),
            'location'     => fake()->city(),
            'is_remote'    => fake()->boolean(),
            'salary'       => fake()->randomFloat(2, 30000, 150000),
            'status'       => fake()->randomElement(['draft', 'published', 'closed']),
            'published_at' => now(),
        ];
    }
}
