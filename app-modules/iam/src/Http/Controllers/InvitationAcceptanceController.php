<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Iam\Data\InvitationAcceptanceData;
use Lahatre\Iam\Http\Requests\InvitationAcceptanceRequest;
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
        $this->invitations->accept(InvitationAcceptanceData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): array => [
            'message' => __('iam::messages.invitation.accepted'),
        ], status: 201);
    }
}
