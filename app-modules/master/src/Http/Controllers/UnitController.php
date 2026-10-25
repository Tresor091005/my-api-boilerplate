<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitUpsertData;
use Lahatre\Master\Http\Requests\UnitFilterRequest;
use Lahatre\Master\Http\Requests\UnitUpsertRequest;
use Lahatre\Master\Http\Resources\UnitGroupResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Services\UnitService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class UnitController
{
    public function __construct(
        protected UnitService $unitService,
    ) {}

    public function index(UnitFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Unit::class);

        $filters = UnitFilterData::fromArray($request->validated());

        $response = $this->unitService->paginate($filters);

        return ApiResponse::cursorPage($response, UnitResource::class);
    }

    public function upsert(UnitUpsertRequest $request): JsonResponse|Response
    {
        Gate::authorize('upsert', Unit::class);

        $data = UnitUpsertData::fromArray($request->validated());

        $response = $this->unitService->upsert($data);

        return ApiResponse::item($response, UnitGroupResource::class);
    }
}
