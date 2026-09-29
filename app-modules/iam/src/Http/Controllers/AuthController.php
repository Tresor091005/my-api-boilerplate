<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\RegistrationData;
use Lahatre\Iam\Data\ResetPasswordData;
use Lahatre\Iam\Http\Requests\ForgotPasswordRequest;
use Lahatre\Iam\Http\Requests\LoginRequest;
use Lahatre\Iam\Http\Requests\OrganizationRegistrationTokenRequest;
use Lahatre\Iam\Http\Requests\RegisterRequest;
use Lahatre\Iam\Http\Requests\ResetPasswordRequest;
use Lahatre\Iam\Http\Requests\SwitchMemberRoleRequest;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Jobs\SendOrganizationRegistrationLink;
use Lahatre\Iam\Jobs\SendPasswordResetLink;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class AuthController
{
    public function __construct(
        protected AuthService $authService,
        protected OrganizationOnboardingService $onboarding,
        protected ResponseResponder $responseResponder,
    ) {}

    public function register(RegisterRequest $request): JsonResponse|Response
    {
        $this->onboarding->register(RegistrationData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): array => [
            'message' => __('iam::messages.auth.organization_registered'),
        ], status: 201);
    }

    public function organizationRegistrationToken(OrganizationRegistrationTokenRequest $request): JsonResponse|Response
    {
        SendOrganizationRegistrationLink::dispatch($request->validated('email'));

        return $this->responseResponder->respond(fn (): array => [
            'message' => __('iam::messages.auth.registration_link_sent'),
        ]);
    }

    /**
     * Authenticate a user and return an AuthResource.
     */
    public function login(LoginRequest $request): JsonResponse|Response
    {
        $response = $this->authService->login(LoginData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => AuthResource::make($response['user'])->withToken($response['token']),
        );
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

        return $this->responseResponder->respond(
            fn (): JsonResource => UserResource::make($response)->withCurrentMemberRoleId(authContext()->memberRole()?->id),
        );
    }

    /**
     * Log out the current user.
     */
    public function logout(): JsonResponse|Response
    {
        $this->authService->logout(authContext()->user());

        return $this->responseResponder->respond(fn (): array => [
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

        return $this->responseResponder->respond(
            fn (): JsonResource => UserResource::make($response)->withCurrentMemberRoleId($request->validated('member_role_id')),
        );
    }

    public function currentPermissions(): JsonResponse|Response
    {
        if (!authContext()->memberRole()) {
            // A user without an active member role has no permissions to serialize.
            return $this->responseResponder->respond(fn (): array => []);
        }

        $response = $this->authService->currentPermissions(
            authContext()->memberRole()
        );

        return $this->responseResponder->respond(fn (): JsonResource => PermissionResource::collection($response));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse|Response
    {
        SendPasswordResetLink::dispatch($request->validated('email'));

        return $this->responseResponder->respond(fn (): array => [
            'message' => __('iam::messages.auth.password_reset_link_sent'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse|Response
    {
        $this->authService->resetPassword(ResetPasswordData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): array => ['detail' => true]);
    }
}
