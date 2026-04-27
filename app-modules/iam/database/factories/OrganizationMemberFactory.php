<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'organization_id' => Organization::factory(),
        ];
    }
}
