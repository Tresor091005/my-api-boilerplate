<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Iam\DTO\ForgotPasswordDTO;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\DTO\ResetPasswordDTO;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Shared\Enums\AuthAccountType;

class AuthController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate a user and return an AuthResource.
     */
    public function login(Request $request, AuthAccountType $type): AuthResource
    {
        $dto = LoginDTO::fromRequest($request);

        return $this->authService->login($type, $dto);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json(authContext()->user());
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
    public function switchUserRole(Request $request): JsonResponse
    {
        // TODO validation missing
        $this->authService->switchUserRole(authContext()->user(), $request->input('role_id'));

        return response()->json([
            'message' => __('iam::messages.auth.role_switched'),
        ]);
    }

    public function forgotPassword(Request $request, AuthAccountType $type): JsonResponse
    {
        $dto = ForgotPasswordDTO::fromRequest($request);

        $token = $this->authService->forgotPassword($type, $dto->email);

        return response()->json(compact('token'));
    }

    public function resetPassword(Request $request, AuthAccountType $type): JsonResponse
    {
        // TODO validation missing
        $dto = ResetPasswordDTO::fromRequest($request);

        $this->authService->resetPassword($type, $dto);

        return response()->json(['detail' => true]);
    }
}
