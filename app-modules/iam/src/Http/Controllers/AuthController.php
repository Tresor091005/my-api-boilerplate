<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Iam\DTO\ForgotPasswordDTO;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\DTO\ResetPasswordDTO;
use Lahatre\Iam\DTO\SwitchMemberRoleDTO;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;

class AuthController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate a user and return an AuthResource.
     */
    public function login(Request $request): AuthResource
    {
        $dto = LoginDTO::fromRequest($request);

        return $this->authService->login($dto);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): UserResource
    {
        $user = authContext()->user();

        // Note: AuthService currently accepts the IAM user model only. If we later support
        // multi-guard authenticatables here, widen the service contract instead of removing this assertion.
        assert($user instanceof User);

        return $this->authService->me(
            user: $user,
            currentMemberRoleId: authContext()->memberRole()?->id
        );
    }

    /**
     * Log out the current user.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout(authContext()->user());

        return response()->json([
            'message' => __('iam::messages.auth.logged_out'),
        ]);
    }

    /**
     * Switch the current user role.
     */
    public function switchMemberRole(Request $request): UserResource
    {
        $user = authContext()->user();

        // Note: AuthService currently accepts the IAM user model only. If we later support
        // multi-guard authenticatables here, widen the service contract instead of removing this assertion.
        assert($user instanceof User);

        return $this->authService->switchMemberRole(
            $user,
            SwitchMemberRoleDTO::fromRequest($request)->member_role_id
        );
    }

    public function currentPermissions(Request $request)
    {
        if (!authContext()->memberRole()) {
            return response()->json([], 200);
        }

        return $this->authService->currentPermissions(
            authContext()->memberRole()
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $dto = ForgotPasswordDTO::fromRequest($request);

        $token = $this->authService->forgotPassword($dto->email);

        return response()->json(['token' => $token]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $dto = ResetPasswordDTO::fromRequest($request);

        $this->authService->resetPassword($dto);

        return response()->json(['detail' => true]);
    }
}
