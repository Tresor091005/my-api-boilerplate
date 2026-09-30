<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Iam\Data\MemberRoleCreateData;
use Lahatre\Iam\Data\MemberRoleDeleteData;
use Lahatre\Iam\Data\MemberRoleUpdateData;
use Lahatre\Iam\Exceptions\MemberRoleException;
use Lahatre\Iam\Exceptions\OrganizationMemberException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;

final class MemberRoleService
{
    public function __construct(private readonly MemberRoleDeletionService $deletions) {}

    /**
     * Add a complete batch of organization roles and their Spatie assignments.
     * Owns the transaction; locks the member before roles, like member removal.
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     *
     * @return Collection<int, MemberRole>
     */
    public function create(OrganizationMember $member, MemberRoleCreateData $data): Collection
    {
        $organizationId = currentOrganizationId();
        $assignments = DB::transaction(function () use ($member, $data, $organizationId): Collection {
            $locked = $this->lockMember($organizationId, $member->id);
            $roles = Role::query()->where('team_id', $organizationId)->where('is_builtin', false)
                ->where('guard_name', config('auth.defaults.guard'))->whereIn('id', $data->roleIds)
                ->orderBy('id')->lockForUpdate()->get();
            if ($data->roleIds === [] || $roles->count() !== count($data->roleIds)) {
                throw MemberRoleException::rolesUnavailable();
            }
            if (MemberRole::query()->where('organization_id', $organizationId)->where('member_id', $locked->id)
                ->whereIn('role_id', $data->roleIds)->exists()) {
                throw MemberRoleException::alreadyAssigned();
            }

            $now = now();
            $rows = [];
            foreach ($roles as $role) {
                $rows[] = [
                    'id'              => (string) Str::uuid7(),
                    'organization_id' => $organizationId,
                    'member_id'       => $locked->id,
                    'role_id'         => $role->id,
                    'is_active'       => $data->isActive,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            MemberRole::query()->insert($rows);
            $assignments = MemberRole::query()->where('organization_id', $organizationId)->where('member_id', $locked->id)
                ->whereIn('id', array_column($rows, 'id'))->orderBy('id')->get();
            $assignmentsByRole = $assignments->keyBy('role_id');
            $previousTeamId = getPermissionsTeamId();
            setPermissionsTeamId($organizationId);
            try {
                foreach ($roles as $role) {
                    $assignmentsByRole->get($role->id)->syncRoles($role);
                }
            } finally {
                setPermissionsTeamId($previousTeamId);
            }

            return $assignments;
        });

        return $assignments->load(responseRelationsToLoad());
    }

    /**
     * Change only the activation state of a complete batch of assignments.
     * Owns the transaction and locks the member before assignments.
     * Spatie assignments remain intact for later reactivation.
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     *
     * @return Collection<int, MemberRole>
     */
    public function update(OrganizationMember $member, MemberRoleUpdateData $data): Collection
    {
        $organizationId = currentOrganizationId();
        $assignments = DB::transaction(function () use ($member, $data, $organizationId): Collection {
            $locked = $this->lockMember($organizationId, $member->id);
            $assignments = MemberRole::query()->where('organization_id', $organizationId)
                ->where('member_id', $locked->id)->whereIn('id', $data->memberRoleIds)
                ->whereHas('role', fn (Builder $query) => $query
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->where(fn (Builder $query) => $query->where('team_id', $organizationId)
                        ->orWhere(fn (Builder $query) => $query->whereNull('team_id')->where('is_builtin', true))))
                ->orderBy('id')->lockForUpdate()->get();
            if ($data->memberRoleIds === [] || $assignments->count() !== count($data->memberRoleIds)) {
                throw MemberRoleException::assignmentsUnavailable();
            }
            if (!$data->isActive) {
                $this->deletions->assertCanRevoke($locked, $assignments);
            }

            $now = now();
            MemberRole::query()->where('organization_id', $organizationId)->where('member_id', $locked->id)
                ->whereIn('id', $assignments->modelKeys())->update(['is_active' => $data->isActive, 'updated_at' => $now]);
            foreach ($assignments as $assignment) {
                $assignment->forceFill(['is_active' => $data->isActive, 'updated_at' => $now])->syncOriginal();
            }

            return $assignments;
        });

        return $assignments->load(responseRelationsToLoad());
    }

    /**
     * Soft-delete a complete batch, allowing the member to have no remaining roles.
     * Owns the transaction and shares the membership lock with grants and removal.
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     */
    public function delete(OrganizationMember $member, MemberRoleDeleteData $data): void
    {
        $organizationId = currentOrganizationId();
        DB::transaction(function () use ($member, $data, $organizationId): void {
            $locked = $this->lockMember($organizationId, $member->id);
            $assignments = MemberRole::query()->where('organization_id', $organizationId)
                ->where('member_id', $locked->id)->whereIn('id', $data->memberRoleIds)
                ->orderBy('id')->lockForUpdate()->get();
            if ($data->memberRoleIds === [] || $assignments->count() !== count($data->memberRoleIds)) {
                throw MemberRoleException::assignmentsUnavailable();
            }
            $this->deletions->delete($locked, $assignments);
        });
    }

    /** @throws OrganizationMemberException */
    private function lockMember(string $organizationId, string $memberId): OrganizationMember
    {
        $member = OrganizationMember::query()->where('organization_id', $organizationId)
            ->whereKey($memberId)->lockForUpdate()->first();
        if (!$member) {
            throw OrganizationMemberException::unavailable();
        }

        return $member;
    }
}
