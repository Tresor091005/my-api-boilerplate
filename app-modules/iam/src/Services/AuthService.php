<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\ResetPasswordData;
use Lahatre\Iam\Exceptions\Auth\InvalidLoginException;
use Lahatre\Iam\Exceptions\Auth\ResetPasswordFailedException;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\User;
use Lahatre\Shared\Models\Authenticatable;

class AuthService
{
    /**
     * Authenticate a user and return an AuthResource.
     *
     * @throws InvalidLoginException
     */
    public function login(LoginData $data): AuthResource
    {
        $user = User::query()
            ->with(['organizationMemberships.memberRoles.role'])
            ->where('email', $data->email)
            ->first();

        if (!$user || !Hash::check($data->password, $user->password)) {
            throw new InvalidLoginException;
        }

        $token = $user->createToken('auth_token', ['*'], now()->addDay());
        $token->accessToken->update([
            'metadata' => [
                'organization_id' => null,
                'member_id'       => null,
                'member_role_id'  => null,
                'role_id'         => null,
            ],
        ]);

        return AuthResource::make($user)->withToken($token->plainTextToken);
    }

    /**
     * Return a UserResource.
     */
    public function me(User $user, ?string $currentMemberRoleId): UserResource
    {
        $user->load(['organizationMemberships.memberRoles.role']);

        return UserResource::make($user)->withCurrentMemberRoleId($currentMemberRoleId);
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
     * Switch the current user role and return the updated UserResource.
     */
    public function switchMemberRole(User $user, string $memberRoleId): UserResource
    {
        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember'])
            ->where('id', $memberRoleId)
            ->first();

        $member = $memberRole?->organizationMember;

        if (!$memberRole || !$member || $member->user_id !== $user->id) {
            throw new ModelNotFoundException()->setModel(MemberRole::class, [$memberRoleId]);
        }

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $token->update([
            'metadata' => [
                'organization_id' => $memberRole->organization_id,
                'member_id'       => $memberRole->member_id,
                'member_role_id'  => $memberRole->id,
                'role_id'         => $memberRole->role_id,
            ],
        ]);

        $user->load(['organizationMemberships.memberRoles.role']);

        return UserResource::make($user)->withCurrentMemberRoleId($memberRole->id);
    }

    public function currentPermissions(MemberRole $memberRole): AnonymousResourceCollection
    {
        return PermissionResource::collection($memberRole->getPermissionsViaRoles());
    }

    /**
     * Forgot password
     */
    public function forgotPassword(string $email): string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return '';
        }

        $token = Password::broker('users')->createToken($user);

        $link = rtrim(config('app.url'), '/').'/auth/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        return $link;
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordData $data): void
    {
        $status = DB::transaction(fn () => Password::broker('users')->reset(
            [
                'email'    => $data->email,
                'password' => $data->password,
                'token'    => $data->token,
            ],
            function (User $user, string $password): void {
                $user->fill(['password' => $password]);
                $user->save();
            }
        ));

        if ($status !== Password::PASSWORD_RESET) {
            throw new ResetPasswordFailedException;
        }
    }
}
