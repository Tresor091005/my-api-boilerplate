<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\User;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * The account of the signed-in user, shown only to its owner.
 */
class UserResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        foreach (self::accountValues() as $key => $value) {
            $resource->field($key, columns: $value['columns'], relations: $value['relations'], computedBy: $value['read']);
        }
    }

    /**
     * The account values, listed once for this Resource and the AuthResource payload: each JSON key
     * with the columns and relations it reads, and how to read it on the user.
     *
     * @return array<string, array{columns: list<string>, relations: list<string>, read: Closure(Model): mixed}>
     */
    public static function accountValues(): array
    {
        return [
            'id'               => self::column('id'),
            'first_name'       => self::column('first_name'),
            'last_name'        => self::column('last_name'),
            'email'            => self::column('email'),
            'profile_complete' => [
                'columns'   => ['first_name', 'last_name'],
                'relations' => [],
                'read'      => fn (Model $user): bool => filled($user->getAttribute('first_name')) && filled($user->getAttribute('last_name')),
            ],
            'default_member_role_id' => self::column('default_member_role_id'),
            // the member role of the current request, not a value of the user
            'current_member_role_id' => [
                'columns'   => [],
                'relations' => [],
                'read'      => fn (Model $user): ?string => authContext()->memberRole()?->id,
            ],
            'member_roles' => [
                'columns'   => ['id', 'default_member_role_id'],
                'relations' => ['organizationMemberships.organization', 'organizationMemberships.memberRoles.role'],
                'read'      => fn (Model $user): array => self::memberRoles($user),
            ],
        ];
    }

    /**
     * @return array{columns: list<string>, relations: list<string>, read: Closure(Model): mixed}
     */
    private static function column(string $column): array
    {
        return ['columns' => [$column], 'relations' => [], 'read' => fn (Model $user): mixed => $user->getAttribute($column)];
    }

    /**
     * The user's usable member roles in every organization, from the loaded memberships.
     *
     * @return list<array<string, mixed>>
     */
    private static function memberRoles(Model $user): array
    {
        if (!$user instanceof User) {
            return [];
        }

        $memberRoles = [];

        /** @var OrganizationMember $membership */
        foreach ($user->getRelation('organizationMemberships') as $membership) {
            if (!$membership->relationLoaded('memberRoles') || !$membership->relationLoaded('organization')) {
                continue;
            }

            $organization = $membership->organization;

            /** @var MemberRole $memberRole */
            foreach ($membership->memberRoles as $memberRole) {
                if (!$memberRole->relationLoaded('role')
                    || !$memberRole->hasValidContextFor($user, $membership, $memberRole->role)) {
                    continue;
                }

                $memberRoles[] = [
                    'id'              => $memberRole->id,
                    'member_id'       => $memberRole->member_id,
                    'organization_id' => $memberRole->organization_id,
                    'role_id'         => $memberRole->role_id,
                    'is_default'      => $memberRole->id === $user->default_member_role_id,
                    'role'            => [
                        'id'          => $memberRole->role->id,
                        'name'        => $memberRole->role->name,
                        'description' => $memberRole->role->description,
                        'is_builtin'  => $memberRole->role->is_builtin,
                    ],
                    'organization' => [
                        'id'   => $organization->id,
                        'name' => $organization->name,
                    ],
                ];
            }
        }

        return $memberRoles;
    }
}
