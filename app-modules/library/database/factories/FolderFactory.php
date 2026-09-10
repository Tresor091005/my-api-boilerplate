<?php

declare(strict_types=1);

namespace Lahatre\Library\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Library\Models\Folder;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<Folder> */
class FolderFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'name'            => $this->faker->unique()->words(2, true),
            'parent_id'       => null,
        ];
    }
}
