<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use App\Models\Company\CompanyMember;
use App\Models\User\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\Exceptions\Auth\InvalidLoginException;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService implements StandaloneService
{
    /**
     * Authenticate a user and return an AuthResource.
     *
     * @throws InvalidLoginException
     */
    public function login(LoginDTO $dto): AuthResource
    {
        $authenticatable = match ($dto->type) {
            'user'           => User::where('email', $dto->email)->first(),
            'company-member' => CompanyMember::where('email', $dto->email)->first(),
            default          => null,
        };

        if (!$authenticatable || !Hash::check($dto->password, $authenticatable->password)) {
            throw new InvalidLoginException();
        }

        $metadata = match ($dto->type) {
            'user'           => ['type' => 'user', 'company_id' => null],
            'company-member' => ['type' => 'agent', 'company_id' => $authenticatable->company_id],
            default          => null,
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
        /** @var \Lahatre\Iam\Auth\PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $metadata = $token->metadata ?? [];
        $metadata['role_id'] = $roleId;

        $token->update(['metadata' => $metadata]);
    }
}
