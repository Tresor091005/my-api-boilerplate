<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Data\OrganizationMemberFilterData;
use Lahatre\Iam\Exceptions\MemberRoleException;
use Lahatre\Iam\Exceptions\OrganizationMemberException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Organization\Contracts\OrganizationInterface;

final class OrganizationMemberService
{
    public function __construct(
        private readonly OrganizationInterface $organizations,
        private readonly MemberRoleDeletionService $memberRoleDeletions,
    ) {}

    public function paginate(OrganizationMemberFilterData $filters): CursorPaginator
    {
        $organizationId = currentOrganizationId();
        $query = OrganizationMember::query()->where('organization_id', $organizationId)
            ->with($this->relationsToLoad($organizationId));

        return stableCursorPaginate($query, $filters);
    }

    /** @throws OrganizationMemberException */
    public function retrieve(OrganizationMember $member): OrganizationMember
    {
        $organizationId = currentOrganizationId();
        if ($member->organization_id !== $organizationId || $member->trashed()) {
            throw OrganizationMemberException::unavailable();
        }

        return $member->load($this->relationsToLoad($organizationId));
    }

    /**
     * Remove a member and their active role assignments in the current organization.
     * Owns the transaction and locks the member before checking protected roles.
     *
     * @throws OrganizationMemberException
     * @throws MemberRoleException
     */
    public function delete(OrganizationMember $member): void
    {
        $organizationId = currentOrganizationId();
        DB::transaction(function () use ($member, $organizationId): void {
            $locked = OrganizationMember::query()->where('organization_id', $organizationId)
                ->whereKey($member->id)->lockForUpdate()->first();
            if (!$locked) {
                throw OrganizationMemberException::unavailable();
            }
            if ($locked->user_id === $this->organizations->findOrganizationById($organizationId)->owner_id) {
                throw OrganizationMemberException::owner();
            }
            $memberRoles = MemberRole::query()->where('organization_id', $organizationId)
                ->where('member_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
            $this->memberRoleDeletions->delete($locked, $memberRoles);
            $locked->delete();
        });
    }

    /** @return array<int|string, string|\Closure> */
    private function relationsToLoad(string $organizationId): array
    {
        $relations = responseRelationsToLoad();
        if (in_array('memberRoles', $relations, true) || in_array('memberRoles.role', $relations, true)) {
            $relations['memberRoles'] = fn (Relation $query) => $query->where('organization_id', $organizationId);
        }
        if (in_array('memberRoles.role', $relations, true)) {
            $relations['memberRoles.role'] = fn (Relation $query) => $query
                ->where('guard_name', config('auth.defaults.guard'))
                ->where(fn ($query) => $query->where('team_id', $organizationId)
                    ->orWhere(fn ($query) => $query->whereNull('team_id')->where('is_builtin', true)));
        }

        return $relations;
    }
}
