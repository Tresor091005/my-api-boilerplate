<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Iam\DTO\LoginDTO;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Services\AuthService;

class AuthController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate a user and return an AuthResource.
     */
    public function login(Request $request, string $type): AuthResource
    {
        $dto = LoginDTO::fromArray(array_merge($request->all(), ['type' => $type]));

        return $this->authService->login($dto);
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
        $this->authService->switchUserRole(authContext()->user(), $request->input('role_id'));

        return response()->json([
            'message' => __('iam::messages.auth.role_switched'),
        ]);
    }
}
