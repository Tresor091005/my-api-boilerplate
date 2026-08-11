<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitSyncData;
use Lahatre\Master\Http\Requests\UnitFilterRequest;
use Lahatre\Master\Http\Requests\UnitSyncRequest;
use Lahatre\Master\Http\Resources\UnitCollection;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Services\UnitService;

class UnitController
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index(UnitFilterRequest $request): UnitCollection
    {
        Gate::authorize('list', Unit::class);

        $filters = UnitFilterData::fromArray($request->validated());

        return $this->unitService->list($filters);
    }

    public function sync(UnitSyncRequest $request): JsonResponse
    {
        Gate::authorize('sync', Unit::class);

        $data = UnitSyncData::fromArray($request->validated());

        $response = $this->unitService->sync($data);

        return response()->json($response);
    }
}
