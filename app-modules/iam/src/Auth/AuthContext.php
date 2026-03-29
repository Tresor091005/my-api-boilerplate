<?php

declare(strict_types=1);

namespace Lahatre\Iam\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Models\Authenticatable;

class AuthContext
{
    protected ?Authenticatable $user = null;

    protected ?Model $organization = null;

    protected ?OrganizationMember $member = null;

    protected ?MemberRole $memberRole = null;

    protected ?Role $role = null;

    public function setContext(Authenticatable $user, ?array $metadata = null): void
    {
        $this->user = $user;

        if (empty($metadata) || empty($metadata['organization_id'])) {
            return;
        }

        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember', 'role'])
            ->where('id', $metadata['member_role_id'] ?? null)
            ->where('member_id', $metadata['member_id'] ?? null)
            ->where('organization_id', $metadata['organization_id'] ?? null)
            ->where('role_id', $metadata['role_id'] ?? null)
            ->whereHas('organizationMember', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->first();

        if (!$memberRole) {
            logger()->warning('Incoherent AuthContext metadata for user {user_id}', [
                'user_id'  => $user->getAuthIdentifier(),
                'metadata' => $metadata,
            ]);

            throw new AuthenticationException('Invalid session context.');
        }

        $this->organization = app(OrganizationInterface::class)->findOrganizationById($metadata['organization_id']);
        $this->member = $memberRole->organizationMember;
        $this->memberRole = $memberRole;
        $this->role = $memberRole->role;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->organization = null;
        $this->member = null;
        $this->memberRole = null;
        $this->role = null;
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function organization(): ?Model
    {
        return $this->organization;
    }

    public function member(): ?OrganizationMember
    {
        return $this->member;
    }

    public function memberRole(): ?MemberRole
    {
        return $this->memberRole;
    }

    public function role(): ?Role
    {
        return $this->role;
    }
}
