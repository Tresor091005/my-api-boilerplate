<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Http\Resources\PermissionResource;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Services\PermissionService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class PermissionController
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(): JsonResponse|Response
    {
        Gate::authorize('list', Permission::class);

        $permissions = $this->permissionService->all();

        return $this->responseResponder->respond(
            fn (): JsonResource => PermissionResource::collection($permissions),
        );
    }
}
