<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\UnitFilterDTO;
use Lahatre\Catalog\DTO\UnitSyncDTO;
use Lahatre\Catalog\Http\Resources\UnitCollection;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Catalog\Services\UnitService;

class UnitController
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index(Request $request): UnitCollection
    {
        Gate::authorize('list', Unit::class);

        $filters = UnitFilterDTO::fromRequest($request);

        return $this->unitService->list($filters);
    }

    public function sync(Request $request): JsonResponse
    {
        Gate::authorize('sync', Unit::class);

        $dto = UnitSyncDTO::fromRequest($request);

        $response = $this->unitService->sync($dto);

        return response()->json($response);
    }
}
