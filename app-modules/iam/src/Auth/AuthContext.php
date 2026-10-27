<?php

declare(strict_types=1);

namespace Lahatre\Iam\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Models\Authenticatable;

class AuthContext
{
    protected ?Authenticatable $user = null;

    protected ?Model $organization = null;

    protected ?OrganizationMember $member = null;

    protected ?MemberRole $memberRole = null;

    protected ?Role $role = null;

    protected bool $platformToken = false;

    protected bool $platformAccess = false;

    /**
     * Resolve fresh access records and publish only a coherent, active context.
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws AuthorizationException
     */
    public function setContext(Authenticatable $user, ?array $metadata = null): void
    {
        $this->clear();

        if (($metadata['platform'] ?? false) === true) {
            $this->user = $user;
            $this->platformToken = true;

            return;
        }

        if ($metadata === null || $metadata === [] || empty($metadata['organization_id'])) {
            $this->user = $user;

            return;
        }

        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember.organization', 'role'])
            ->where('id', $metadata['member_role_id'] ?? null)
            ->where('member_id', $metadata['member_id'] ?? null)
            ->where('organization_id', $metadata['organization_id'])
            ->where('role_id', $metadata['role_id'] ?? null)
            ->first();

        $member = $memberRole?->organizationMember;
        $role = $memberRole?->role;

        if (!$memberRole?->hasValidContextFor($user, $member, $role)) {
            logger()->warning(__('iam::messages.auth.incoherent_auth_metadata', ['user_id' => $user->getAuthIdentifier()]), [
                'user_id'  => $user->getAuthIdentifier(),
                'metadata' => $metadata,
            ]);

            throw new AuthorizationException(__('iam::exceptions.auth.invalid_session_context'));
        }

        $this->user = $user;
        $this->organization = $member->organization;
        $this->member = $member;
        $this->memberRole = $memberRole;
        $this->role = $role;
    }

    /**
     * Publish a member role the caller already resolved and validated, with its member,
     * organization and role loaded, without querying again.
     */
    public function useMemberRole(Authenticatable $user, MemberRole $memberRole): void
    {
        $member = $memberRole->organizationMember;

        $this->clear();
        $this->user = $user;
        $this->organization = $member->organization;
        $this->member = $member;
        $this->memberRole = $memberRole;
        $this->role = $memberRole->role;
    }

    /** Publish a platform context whose membership the caller just verified. */
    public function usePlatform(Authenticatable $user): void
    {
        $this->clear();
        $this->user = $user;
        $this->platformToken = true;
        $this->platformAccess = true;
    }

    /** Record that the platform token's user is still a platform user, as checked once per request. */
    public function confirmPlatformAccess(): void
    {
        $this->platformAccess = $this->platformToken;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->organization = null;
        $this->member = null;
        $this->memberRole = null;
        $this->role = null;
        $this->platformToken = false;
        $this->platformAccess = false;
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

    public function hasPlatformToken(): bool
    {
        return $this->platformToken;
    }

    public function hasPlatformAccess(): bool
    {
        return $this->platformAccess;
    }
}
