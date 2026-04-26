<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Models\Taggable;

/**
 * @extends Factory<Taggable>
 */
class TaggableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tag_id'        => Tag::factory(),
            'taggable_type' => 'master_tag',
            'taggable_id'   => fake()->uuid(),
        ];
    }
}
