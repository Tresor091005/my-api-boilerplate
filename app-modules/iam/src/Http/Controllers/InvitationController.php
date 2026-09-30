<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\InvitationData;
use Lahatre\Iam\Data\InvitationFilterData;
use Lahatre\Iam\Http\Requests\InvitationCreateRequest;
use Lahatre\Iam\Http\Requests\InvitationFilterRequest;
use Lahatre\Iam\Http\Requests\InvitationRolesRequest;
use Lahatre\Iam\Http\Resources\InvitationCollection;
use Lahatre\Iam\Http\Resources\InvitationResource;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Services\InvitationService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InvitationController
{
    public function __construct(
        protected InvitationService $invitations,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(InvitationFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Invitation::class);
        $invitations = $this->invitations->paginate(InvitationFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => InvitationCollection::make($invitations));
    }

    public function store(InvitationCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Invitation::class);
        $invitation = $this->invitations->create(InvitationData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => InvitationResource::make($invitation), status: 201);
    }

    public function show(Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('retrieve', $invitation);
        $invitation = $this->invitations->retrieve($invitation);

        return $this->responseResponder->respond(fn (): JsonResource => InvitationResource::make($invitation));
    }

    public function replaceRoles(InvitationRolesRequest $request, Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('update', $invitation);
        $invitation = $this->invitations->replaceRoles($invitation, $request->validated('role_ids'));

        return $this->responseResponder->respond(fn (): JsonResource => InvitationResource::make($invitation));
    }

    public function resendEmail(Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('update', $invitation);
        $invitation = $this->invitations->resend($invitation);

        return $this->responseResponder->respond(fn (): JsonResource => InvitationResource::make($invitation));
    }

    public function destroy(Invitation $invitation): Response
    {
        Gate::authorize('delete', $invitation);
        $this->invitations->delete($invitation);

        return response()->noContent();
    }
}
