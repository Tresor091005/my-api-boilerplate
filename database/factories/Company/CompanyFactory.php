<?php

declare(strict_types=1);

namespace Database\Factories\Company;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => fake()->company(),
            'description' => fake()->paragraph(),
            'website'     => fake()->url(),
            'logo_path'   => fake()->imageUrl(200, 200, 'business'),
        ];
    }
}
