<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Services\PermissionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class PermissionController
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {}

    public function index(): JsonResponse|Response
    {
        Gate::authorize('list', Permission::class);

        $permissions = $this->permissionService->all();

        return ApiResponse::list($permissions, PermissionResource::class);
    }
}
