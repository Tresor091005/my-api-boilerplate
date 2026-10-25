<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources\Loaders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\Role;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\VirtualIncludeLoader;

/**
 * The role of each member role, or null when the role is deleted or not usable in the member
 * role's organization.
 */
final class AssignmentRoles implements VirtualIncludeLoader
{
    /**
     * @param  Collection<int, Model>  $parents
     * @return array<int|string, Role|null>
     */
    public function load(Collection $parents, BuiltValues $built): array
    {
        $roles = Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->whereIn('id', $parents->map(fn (Model $memberRole): mixed => $memberRole->getAttribute('role_id'))->unique()->values()->all())
            ->get()
            ->keyBy('id');

        $children = [];

        foreach ($parents as $memberRole) {
            $role = $roles->get($memberRole->getAttribute('role_id'));

            $children[$memberRole->getKey()] = $memberRole instanceof MemberRole && $memberRole->canUseRole($role) ? $role : null;
        }

        return $children;
    }
}
