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

        if ($metadata === null || $metadata === [] || empty($metadata['organization_id'])) {
            return;
        }

        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember'])
            ->where('id', $metadata['member_role_id'] ?? null)
            ->where('member_id', $metadata['member_id'] ?? null)
            ->where('organization_id', $metadata['organization_id'])
            ->where('role_id', $metadata['role_id'] ?? null)
            ->first();

        $member = $memberRole?->organizationMember;

        if (!$memberRole || !$member || $member->user_id !== $user->id) {
            logger()->warning(__('iam::messages.auth.incoherent_auth_metadata', ['user_id' => $user->getAuthIdentifier()]), [
                'user_id'  => $user->getAuthIdentifier(),
                'metadata' => $metadata,
            ]);

            throw new AuthenticationException(__('iam::exceptions.auth.invalid_session_context'));
        }

        $this->organization = app(OrganizationInterface::class)->findOrganizationById($metadata['organization_id']);
        $this->member = $member;
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
