<?php

declare(strict_types=1);

namespace Lahatre\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'   => Organization::factory(),
            'enable_currencies' => ['XOF'],
        ];
    }
}
