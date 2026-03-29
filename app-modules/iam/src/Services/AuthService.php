<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\DTO\ResetPasswordDTO;
use Lahatre\Iam\Exceptions\Auth\InvalidLoginException;
use Lahatre\Iam\Exceptions\Auth\ResetPasswordFailedException;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\User;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Models\Authenticatable;

class AuthService implements StandaloneService
{
    /**
     * Authenticate a user and return an AuthResource.
     *
     * @throws InvalidLoginException
     */
    public function login(LoginDTO $dto): AuthResource
    {
        $authenticatable = User::query()
            ->with(['organizationMemberships.memberRoles.role'])
            ->where('email', $dto->email)
            ->first();

        if (!$authenticatable || !Hash::check($dto->password, $authenticatable->password)) {
            throw new InvalidLoginException();
        }

        $token = $authenticatable->createToken('auth_token', ['*'], now()->addDay());
        $token->accessToken->update([
            'metadata' => [
                'organization_id' => null,
                'member_id'       => null,
                'member_role_id'  => null,
                'role_id'         => null,
            ],
        ]);

        return AuthResource::make($authenticatable)->withToken($token->plainTextToken);
    }

    /**
     * Return a UserResource.
     */
    public function me(Authenticatable $user, ?string $currentMemberRoleId): UserResource
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
    public function switchMemberRole(Authenticatable $user, string $memberRoleId): UserResource
    {
        /** @var MemberRole|null $memberRole */
        $memberRole = MemberRole::query()
            ->with(['organizationMember', 'role'])
            ->whereKey($memberRoleId)
            ->whereHas(
                'organizationMember',
                fn ($query) => $query->where('user_id', $user->getAuthIdentifier())
            )
            ->first();

        if (!$memberRole) {
            throw new ModelNotFoundException();
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

        $link = 'http://localhost:8000/auth/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        return $link;
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $status = DB::transaction(fn () => Password::broker('users')->reset(
            [
                'email'    => $dto->email,
                'password' => $dto->password,
                'token'    => $dto->token,
            ],
            function (Authenticatable $user, string $password): void {
                $user->fill(['password' => $password]);
                $user->save();
            }
        ));

        if ($status !== Password::PASSWORD_RESET) {
            throw new ResetPasswordFailedException();
        }
    }
}
