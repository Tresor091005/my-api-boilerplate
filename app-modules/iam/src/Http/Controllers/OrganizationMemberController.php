<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\OrganizationMemberFilterData;
use Lahatre\Iam\Data\OrganizationMemberUpdateData;
use Lahatre\Iam\Http\Requests\OrganizationMemberFilterRequest;
use Lahatre\Iam\Http\Requests\OrganizationMemberUpdateRequest;
use Lahatre\Iam\Http\Resources\OrganizationMemberResource;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Services\OrganizationMemberService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMemberController
{
    public function __construct(
        protected OrganizationMemberService $members,
    ) {}

    public function index(OrganizationMemberFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', OrganizationMember::class);
        $members = $this->members->paginate(OrganizationMemberFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($members, OrganizationMemberResource::class);
    }

    public function show(OrganizationMember $organizationMember): JsonResponse|Response
    {
        Gate::authorize('retrieve', $organizationMember);
        $member = $this->members->retrieve($organizationMember);

        return ApiResponse::item($member, OrganizationMemberResource::class);
    }

    public function destroy(OrganizationMember $organizationMember): Response
    {
        Gate::authorize('delete', $organizationMember);
        $this->members->delete($organizationMember);

        return ApiResponse::noContent();
    }

    public function update(OrganizationMemberUpdateRequest $request, OrganizationMember $organizationMember): JsonResponse|Response
    {
        Gate::authorize('update', $organizationMember);
        $member = $this->members->update($organizationMember, OrganizationMemberUpdateData::fromArray($request->validated()));

        return ApiResponse::item($member, OrganizationMemberResource::class);
    }
}
