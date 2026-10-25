<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\OrganizationRegistrationData;
use Lahatre\Iam\Data\UserUpdateData;
use Lahatre\Iam\Http\Requests\AuthenticatedOrganizationCreateRequest;
use Lahatre\Iam\Http\Requests\OrganizationRegistrationRequest;
use Lahatre\Iam\Http\Requests\OrganizationRegistrationTokenRequest;
use Lahatre\Iam\Http\Requests\SwitchMemberRoleRequest;
use Lahatre\Iam\Http\Requests\UserUpdateRequest;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Organization\Data\OrganizationData;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController
{
    public function __construct(
        protected AuthService $authService,
        protected OrganizationOnboardingService $onboarding,
    ) {}

    public function registerOrganization(OrganizationRegistrationRequest $request): JsonResponse|Response
    {
        $this->onboarding->registerOrganization(OrganizationRegistrationData::fromArray($request->validated()));

        return response()->json([
            'message' => __('iam::messages.auth.organization_registered'),
        ], 201);
    }

    public function organizationRegistrationToken(OrganizationRegistrationTokenRequest $request): JsonResponse|Response
    {
        $this->onboarding->requestRegistrationToken($request->validated('email'));

        return response()->json([
            'message' => __('iam::messages.auth.registration_link_sent'),
        ]);
    }

    public function createOrganization(AuthenticatedOrganizationCreateRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        Gate::authorize('createOrganization', $user);
        $this->onboarding->createForUser($user, OrganizationData::fromArray($request->validated(), $user->id));

        return response()->json([
            'message' => __('iam::messages.auth.organization_created'),
        ], 201);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse|Response
    {
        $user = authContext()->user();

        // Note: AuthService currently accepts the IAM user model only. If we later support
        // multi-guard authenticatables here, widen the service contract instead of removing this assertion.
        assert($user instanceof User);

        $response = $this->authService->me($user);

        return ApiResponse::item($response, UserResource::class);
    }

    public function update(UserUpdateRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        Gate::authorize('update', $user);
        $response = $this->authService->update($user, UserUpdateData::fromArray($request->validated()));

        return ApiResponse::item($response, UserResource::class);
    }

    /**
     * Log out the current user.
     */
    public function logout(): JsonResponse|Response
    {
        $this->authService->logout(authContext()->user());

        return response()->json([
            'message' => __('iam::messages.auth.logged_out'),
        ]);
    }

    /**
     * Switch the current user role.
     */
    public function switchMemberRole(SwitchMemberRoleRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();

        // Note: AuthService currently accepts the IAM user model only. If we later support
        // multi-guard authenticatables here, widen the service contract instead of removing this assertion.
        assert($user instanceof User);

        $response = $this->authService->switchMemberRole(
            $user,
            $request->validated('member_role_id'),
        );

        return ApiResponse::item($response, UserResource::class);
    }

    public function currentPermissions(): JsonResponse|Response
    {
        $memberRole = authContext()->memberRole();

        // a user without an active member role has no permissions
        $response = $memberRole ? $this->authService->currentPermissions($memberRole) : new Collection;

        return ApiResponse::list($response, PermissionResource::class);
    }
}
