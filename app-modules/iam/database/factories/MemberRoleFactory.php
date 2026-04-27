<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<MemberRole>
 */
class MemberRoleFactory extends Factory
{
    protected $model = MemberRole::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'member_id'       => OrganizationMember::factory(),
            'role_id'         => Role::factory(),
        ];
    }
}
