<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Models\Authenticatable;

class AuthService
{
    /**
     * Issue the common Sanctum session after the caller proves identity.
     * The caller owns any surrounding authentication transaction.
     *
     * @return array{user: User, token: string}
     */
    public function issueToken(User $user, SessionData $session, string $authenticationMethod = 'email_otp'): array
    {
        $token = $user->createToken('auth_token', ['*'], now()->addDay());
        $token->accessToken->update([
            'metadata' => [
                'organization_id' => null,
                'member_id'       => null,
                'member_role_id'  => null,
                'role_id'         => null,
                'session'         => [
                    'authentication_method' => $authenticationMethod,
                    'last_request'          => [...$session->toArray(), 'at' => now()->toISOString()],
                ],
            ],
        ]);
        $user->load(responseRelationsToLoad());

        return ['user' => $user, 'token' => $token->plainTextToken];
    }

    /**
     * Return the authenticated user.
     */
    public function me(User $user): User
    {
        $user->load(responseRelationsToLoad());

        return $user;
    }

    /**
     * Log out the current user by deleting their access token.
     */
    public function logout(Authenticatable $user): void
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $token->delete();
    }

    /**
     * Switch the current user role and return the updated user.
     */
    public function switchMemberRole(User $user, string $memberRoleId): User
    {
        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember', 'role'])
            ->where('id', $memberRoleId)
            ->first();

        $member = $memberRole?->organizationMember;
        $role = $memberRole?->role;

        if (!$memberRole || !$member || !$role
            || !$memberRole->is_active || !$member->is_active || !$role->is_active
            || $member->user_id !== $user->id
            || $member->organization_id !== $memberRole->organization_id
            || ($role->team_id !== null && $role->team_id !== $memberRole->organization_id)
            || $role->guard_name !== config('auth.defaults.guard')) {
            throw new ModelNotFoundException()->setModel(MemberRole::class, [$memberRoleId]);
        }

        app(OrganizationInterface::class)->findOrganizationById($memberRole->organization_id);

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $metadata = DB::transaction(function () use ($user, $token, $memberRole): array {
            $locked = $user->tokens()->whereKey($token->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'metadata' => array_replace($locked->getAttribute('metadata') ?? [], [
                    'organization_id' => $memberRole->organization_id,
                    'member_id'       => $memberRole->member_id,
                    'member_role_id'  => $memberRole->id,
                    'role_id'         => $memberRole->role_id,
                ]),
            ]);

            return $locked->getAttribute('metadata');
        });
        $token->setAttribute('metadata', $metadata);
        $token->syncOriginalAttribute('metadata');

        $user->load(responseRelationsToLoad());

        return $user;
    }

    public function currentPermissions(MemberRole $memberRole): Collection
    {
        return $memberRole->getPermissionsViaRoles();
    }
}
