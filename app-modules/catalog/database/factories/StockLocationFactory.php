<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\StockLocation;

/**
 * @extends Factory<StockLocation>
 */
class StockLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'handle'          => fake()->unique()->slug(2),
            'name'            => fake()->unique()->city().' Stock Location',
        ];
    }
}
