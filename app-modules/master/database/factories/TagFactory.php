<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Tag;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;
use Lahatre\Shared\Support\HandleGenerator;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $name = str($this->faker->unique()->words(2, true))->normalize()->value();
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'name'            => $name,
            'slug'            => HandleGenerator::generate(
                name: $name,
                table: 'master_tags',
                column: 'slug',
                extra: [
                    'organization_id' => $organizationId,
                ],
            ),
            'type'      => '',
            'order_col' => 0,
        ];
    }
}
