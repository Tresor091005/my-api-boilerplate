<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\ResetPasswordData;
use Lahatre\Iam\Http\Requests\ForgotPasswordRequest;
use Lahatre\Iam\Http\Requests\LoginRequest;
use Lahatre\Iam\Http\Requests\ResetPasswordRequest;
use Lahatre\Iam\Http\Requests\SwitchMemberRoleRequest;
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
    public function login(LoginRequest $request): AuthResource
    {
        return $this->authService->login(LoginData::fromArray($request->validated()));
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
    public function switchMemberRole(SwitchMemberRoleRequest $request): UserResource
    {
        $user = authContext()->user();

        // Note: AuthService currently accepts the IAM user model only. If we later support
        // multi-guard authenticatables here, widen the service contract instead of removing this assertion.
        assert($user instanceof User);

        return $this->authService->switchMemberRole(
            $user,
            $request->validated('member_role_id'),
        );
    }

    public function currentPermissions(): AnonymousResourceCollection|JsonResponse
    {
        if (!authContext()->memberRole()) {
            return response()->json([], 200);
        }

        return $this->authService->currentPermissions(
            authContext()->memberRole()
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $token = $this->authService->forgotPassword($request->validated('email'));

        return response()->json(['token' => $token]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(ResetPasswordData::fromArray($request->validated()));

        return response()->json(['detail' => true]);
    }
}
