<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\MemberRoleCreateData;
use Lahatre\Iam\Data\MemberRoleDeleteData;
use Lahatre\Iam\Http\Requests\MemberRoleCreateRequest;
use Lahatre\Iam\Http\Requests\MemberRoleDeleteRequest;
use Lahatre\Iam\Http\Resources\MemberRoleCollection;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Services\MemberRoleService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class MemberRoleController
{
    public function __construct(
        protected MemberRoleService $memberRoles,
        protected ResponseResponder $responseResponder,
    ) {}

    public function store(MemberRoleCreateRequest $request, OrganizationMember $organizationMember): JsonResponse|Response
    {
        Gate::authorize('update', $organizationMember);
        $assignments = $this->memberRoles->create($organizationMember, MemberRoleCreateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => MemberRoleCollection::make($assignments), status: 201);
    }

    public function destroy(MemberRoleDeleteRequest $request, OrganizationMember $organizationMember): Response
    {
        Gate::authorize('update', $organizationMember);
        $this->memberRoles->delete($organizationMember, MemberRoleDeleteData::fromArray($request->validated()));

        return response()->noContent();
    }
}
