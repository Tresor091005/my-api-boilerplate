<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\InvitationData;
use Lahatre\Iam\Data\InvitationFilterData;
use Lahatre\Iam\Http\Requests\InvitationCreateRequest;
use Lahatre\Iam\Http\Requests\InvitationFilterRequest;
use Lahatre\Iam\Http\Requests\InvitationRolesRequest;
use Lahatre\Iam\Http\Resources\InvitationResource;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Services\InvitationService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class InvitationController
{
    public function __construct(
        protected InvitationService $invitations,
    ) {}

    public function index(InvitationFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Invitation::class);
        $invitations = $this->invitations->paginate(InvitationFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($invitations, InvitationResource::class);
    }

    public function store(InvitationCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Invitation::class);
        $invitation = $this->invitations->create(InvitationData::fromArray($request->validated()));

        return ApiResponse::item($invitation, InvitationResource::class, 201);
    }

    public function show(Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('retrieve', $invitation);
        $invitation = $this->invitations->retrieve($invitation);

        return ApiResponse::item($invitation, InvitationResource::class);
    }

    public function replaceRoles(InvitationRolesRequest $request, Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('update', $invitation);
        $invitation = $this->invitations->replaceRoles($invitation, $request->validated('role_ids'));

        return ApiResponse::item($invitation, InvitationResource::class);
    }

    public function resendEmail(Invitation $invitation): JsonResponse|Response
    {
        Gate::authorize('update', $invitation);
        $invitation = $this->invitations->resend($invitation);

        return ApiResponse::item($invitation, InvitationResource::class);
    }

    public function destroy(Invitation $invitation): Response
    {
        Gate::authorize('delete', $invitation);
        $this->invitations->delete($invitation);

        return ApiResponse::noContent();
    }
}
