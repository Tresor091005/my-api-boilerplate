<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\InvitationAcceptanceData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Http\Requests\AuthenticatedInvitationAcceptanceRequest;
use Lahatre\Iam\Http\Requests\InvitationAcceptanceRequest;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\InvitationService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InvitationAcceptanceController
{
    public function __construct(
        protected InvitationService $invitations,
        protected ResponseResponder $responseResponder,
    ) {}

    public function store(InvitationAcceptanceRequest $request): JsonResponse|Response
    {
        $result = $this->invitations->accept(InvitationAcceptanceData::fromArray($request->validated()), SessionData::fromArray([
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]));

        return $this->responseResponder->respond(
            fn (): AuthResource => AuthResource::make($result['user'])->withToken($result['token']), status: 201,
        );
    }

    public function storeForUser(AuthenticatedInvitationAcceptanceRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        Gate::authorize('update', $user);
        $this->invitations->acceptForUser($user, $request->validated('token'));

        return $this->responseResponder->respond(fn (): array => [
            'message' => __('iam::messages.invitation.joined'),
        ], status: 201);
    }
}
