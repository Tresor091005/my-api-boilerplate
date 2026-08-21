<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Label;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;
use Lahatre\Shared\Support\HandleGenerator;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $value = str($this->faker->unique()->words(2, true))->normalize()->value();
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'value'           => $value,
            'slug'            => HandleGenerator::generate(
                name: $value,
                table: 'master_labels',
                column: 'slug',
                extra: [
                    'organization_id' => $organizationId,
                ],
            ),
            'group'     => '',
            'order_col' => 0,
        ];
    }
}
