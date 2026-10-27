<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Data\UserUpdateData;
use Lahatre\Iam\Exceptions\MemberRoleException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\User;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Models\Authenticatable;

class AuthService
{
    public function __construct(private readonly SessionService $sessions, private readonly PlatformAccessService $platformAccess) {}

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
                'platform'        => false,
                'organization_id' => null,
                'member_id'       => null,
                'member_role_id'  => null,
                'role_id'         => null,
                'session'         => [
                    'authentication_method' => $authenticationMethod,
                    'enrichment_key'        => $session->fingerprint(),
                    'last_request'          => [...$session->toArray(), 'at' => now()->toISOString(), 'device' => null, 'location' => null],
                ],
            ],
        ]);
        $this->sessions->queueEnrichment($token->accessToken, $session);
        // the auth payload shows the user's member roles in every organization
        $user->load(['organizationMemberships.organization', 'organizationMemberships.memberRoles.role']);

        return ['user' => $user, 'token' => $token->plainTextToken];
    }

    /**
     * Return the authenticated user.
     */
    public function me(User $user): User
    {
        return $user;
    }

    public function update(User $user, UserUpdateData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if (!$data->defaultMemberRoleId instanceof MissingValue && $data->defaultMemberRoleId !== null) {
                try {
                    $this->findAccessibleMemberRole($user, $data->defaultMemberRoleId);
                } catch (ModelNotFoundException) {
                    throw MemberRoleException::defaultUnavailable();
                }
            }
            $user->update(withoutMissing([
                'first_name'             => $data->firstName,
                'last_name'              => $data->lastName,
                'default_member_role_id' => $data->defaultMemberRoleId,
            ]));

            return $user->refresh();
        });
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
     * Switch the current user role and return the updated user. The rest of the request
     * runs as the new member role: the auth context is updated without another query.
     */
    public function switchMemberRole(User $user, string $memberRoleId): User
    {
        $memberRole = $this->findAccessibleMemberRole($user, $memberRoleId);

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $metadata = DB::transaction(function () use ($user, $token, $memberRole): array {
            $locked = $user->tokens()->whereKey($token->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'metadata' => array_replace($locked->getAttribute('metadata') ?? [], [
                    'platform'        => false,
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
        // the rest of this request, and its response, run as the new member role
        authContext()->useMemberRole($user, $memberRole);

        return $user;
    }

    /**
     * Switch the current token to the platform level, dropping its organization context.
     * The rest of the request runs in the platform context.
     *
     * @throws AuthorizationException
     */
    public function switchToPlatform(User $user): User
    {
        if (!$this->platformAccess->isPlatformUser($user)) {
            throw new AuthorizationException(__('iam::exceptions.platform.access_required'));
        }

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $metadata = DB::transaction(function () use ($user, $token): array {
            $locked = $user->tokens()->whereKey($token->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'metadata' => array_replace($locked->getAttribute('metadata') ?? [], [
                    'platform'        => true,
                    'organization_id' => null,
                    'member_id'       => null,
                    'member_role_id'  => null,
                    'role_id'         => null,
                ]),
            ]);

            return $locked->getAttribute('metadata');
        });
        $token->setAttribute('metadata', $metadata);
        $token->syncOriginalAttribute('metadata');
        authContext()->usePlatform($user);

        return $user;
    }

    public function currentPermissions(MemberRole $memberRole): Collection
    {
        return $memberRole->getPermissionsViaRoles();
    }

    private function findAccessibleMemberRole(User $user, string $memberRoleId): MemberRole
    {
        $memberRole = MemberRole::query()
            ->whereHas('organizationMember', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['organizationMember.organization', 'role'])->whereKey($memberRoleId)->first();

        if (!$memberRole?->hasValidContextFor($user, $memberRole->organizationMember, $memberRole->role)) {
            throw new ModelNotFoundException()->setModel(MemberRole::class, [$memberRoleId]);
        }

        return $memberRole;
    }
}
