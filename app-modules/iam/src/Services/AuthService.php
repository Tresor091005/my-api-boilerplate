<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\DTO\ResetPasswordDTO;
use Lahatre\Iam\Exceptions\Auth\InvalidLoginException;
use Lahatre\Iam\Exceptions\Auth\ResetPasswordFailedException;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Enums\AuthAccountType;
use Lahatre\Shared\Models\Authenticatable;

class AuthService implements StandaloneService
{
    /**
     * Authenticate a user and return an AuthResource.
     *
     * @throws InvalidLoginException
     */
    public function login(AuthAccountType $type, LoginDTO $dto): AuthResource
    {
        /** @var Authenticatable|null $authenticatable */
        $authenticatable = $type->model()::where('email', $dto->email)->first();

        if (!$authenticatable || !Hash::check($dto->password, $authenticatable->password)) {
            throw new InvalidLoginException();
        }

        $metadata = match ($type->value) {
            'user'           => ['type' => 'user', 'company_id' => null],
            'company-member' => ['type' => 'agent', 'company_id' => data_get($authenticatable, 'company_id')],
        };

        $token = $authenticatable->createToken('auth_token', ['*'], now()->addDay());
        $token->accessToken->update(['metadata' => $metadata]);

        return AuthResource::make($authenticatable)->withToken($token->plainTextToken);
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
     * Switch the current user role.
     */
    public function switchUserRole(Authenticatable $user, string $roleId): void
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $metadata = $token->metadata ?? [];
        $metadata['role_id'] = $roleId;

        $token->update(['metadata' => $metadata]);
    }

    /**
     * Forgot password
     */
    public function forgotPassword(AuthAccountType $type, string $email): string
    {
        $class = $type->model();
        $user = $class::where('email', $email)->first();

        if (!$user) {
            return '';
        }

        $token = Password::broker($type->authProvider())->createToken($user);

        $link = 'http://localhost:8000/auth/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        return $link;
    }

    /**
     * Reset password
     */
    public function resetPassword(AuthAccountType $type, ResetPasswordDTO $dto): void
    {
        $status = DB::transaction(fn () => Password::broker($type->authProvider())->reset(
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
