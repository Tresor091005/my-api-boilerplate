<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\Collection;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Exceptions\MemberRoleException;
use Lahatre\Iam\Exceptions\OrganizationMemberException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Organization\Contracts\OrganizationInterface;

final class MemberRoleDeletionService
{
    public function __construct(private readonly OrganizationInterface $organizations) {}

    /**
     * Clear Spatie roles before soft-deleting assignments in the current organization.
     * Protects the owner's Administrator assignment before any mutation.
     * The caller owns the transaction and locks the member and assignments.
     *
     * @param  Collection<int, MemberRole>  $assignments
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     */
    public function delete(OrganizationMember $member, Collection $assignments): void
    {
        $this->assertCanRevoke($member, $assignments);
        $organizationId = currentOrganizationId();
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($organizationId);
        try {
            foreach ($assignments as $assignment) {
                $assignment->syncRoles([]);
            }
            MemberRole::query()->where('organization_id', $organizationId)->where('member_id', $member->id)
                ->whereIn('id', $assignments->modelKeys())->delete();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Protect the owner's Administrator access before deletion or deactivation.
     * The caller owns the transaction and locks the member and assignments.
     *
     * @param  Collection<int, MemberRole>  $assignments
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     */
    public function assertCanRevoke(OrganizationMember $member, Collection $assignments): void
    {
        $organizationId = currentOrganizationId();
        if ($member->organization_id !== $organizationId || $member->trashed()) {
            throw OrganizationMemberException::unavailable();
        }
        foreach ($assignments as $assignment) {
            if ($assignment->organization_id !== $organizationId || $assignment->member_id !== $member->id || $assignment->trashed()) {
                throw MemberRoleException::assignmentsUnavailable();
            }
        }
        if ($member->user_id === $this->organizations->findOrganizationById($organizationId)->owner_id
            && Role::query()->whereNull('team_id')->where('is_builtin', true)
                ->where('guard_name', config('auth.defaults.guard'))->where('name', SysRole::Administrator->value)
                ->whereIn('id', $assignments->pluck('role_id'))->exists()) {
            throw MemberRoleException::ownerAdministratorProtected();
        }
    }
}
