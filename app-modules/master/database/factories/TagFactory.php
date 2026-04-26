<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Tag;
use Lahatre\Shared\Support\HandleGenerator;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        $name = str($this->faker->unique()->words(2, true))->normalize()->value();
        $organizationId = getPermissionsTeamId() ?: (string) Str::uuid7();

        if (!getPermissionsTeamId()) {
            DB::table('organization_organizations')->insert([
                'id'         => $organizationId,
                'name'       => 'Factory Organization '.$organizationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
