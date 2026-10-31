<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Data\OrganizationMemberFilterData;
use Lahatre\Iam\Data\OrganizationMemberUpdateData;
use Lahatre\Iam\Exceptions\MemberRoleException;
use Lahatre\Iam\Exceptions\OrganizationMemberException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Organization\Contracts\OrganizationInterface;

final readonly class OrganizationMemberService
{
    public function __construct(
        private OrganizationInterface $organizations,
        private MemberRoleDeletionService $memberRoleDeletions,
    ) {}

    public function paginate(OrganizationMemberFilterData $filters): CursorPaginator
    {
        $organizationId = currentOrganizationId();
        $query = OrganizationMember::query()->where('organization_id', $organizationId);

        return stableCursorPaginate($query, $filters);
    }

    /** @throws OrganizationMemberException */
    public function retrieve(OrganizationMember $member): OrganizationMember
    {
        $organizationId = currentOrganizationId();
        if ($member->organization_id !== $organizationId || $member->trashed()) {
            throw OrganizationMemberException::unavailable();
        }

        return $member;
    }

    /**
     * Change membership activation without modifying its role assignments.
     * Owns the transaction and shares the membership lock with role mutations.
     *
     * @throws OrganizationMemberException
     */
    public function update(OrganizationMember $member, OrganizationMemberUpdateData $data): OrganizationMember
    {
        $organizationId = currentOrganizationId();
        $updated = DB::transaction(function () use ($member, $data, $organizationId): OrganizationMember {
            $locked = OrganizationMember::query()->where('organization_id', $organizationId)
                ->whereKey($member->id)->lockForUpdate()->first();
            if (!$locked) {
                throw OrganizationMemberException::unavailable();
            }
            if (!$data->isActive && $locked->user_id === $this->organizations->findOrganizationById($organizationId)->owner_id) {
                throw OrganizationMemberException::owner();
            }
            $locked->is_active = $data->isActive;
            $locked->save();

            return $locked;
        });

        return $updated;
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
}
