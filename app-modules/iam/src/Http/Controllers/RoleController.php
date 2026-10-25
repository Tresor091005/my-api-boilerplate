<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\RoleData;
use Lahatre\Iam\Data\RoleFilterData;
use Lahatre\Iam\Http\Requests\RoleCreateRequest;
use Lahatre\Iam\Http\Requests\RoleFilterRequest;
use Lahatre\Iam\Http\Requests\RoleUpdateRequest;
use Lahatre\Iam\Http\Resources\RoleResource;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Services\RoleService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleController
{
    public function __construct(
        protected RoleService $roleService,
    ) {}

    public function index(RoleFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Role::class);
        $roles = $this->roleService->paginate(RoleFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($roles, RoleResource::class);
    }

    public function store(RoleCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Role::class);
        $role = $this->roleService->create(RoleData::fromArray($request->validated()));

        return ApiResponse::item($role, RoleResource::class, 201);
    }

    public function show(Role $role): JsonResponse|Response
    {
        Gate::authorize('retrieve', $role);
        $role = $this->roleService->retrieve($role);

        return ApiResponse::item($role, RoleResource::class);
    }

    public function update(RoleUpdateRequest $request, Role $role): JsonResponse|Response
    {
        Gate::authorize('update', $role);
        $role = $this->roleService->update(
            $role,
            RoleData::fromArray($request->validated(), ['name', 'description', 'permission_ids', 'is_active']),
        );

        return ApiResponse::item($role, RoleResource::class);
    }

    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);
        $this->roleService->delete($role);

        return ApiResponse::noContent();
    }
}
