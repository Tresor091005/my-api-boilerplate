<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitUpsertData;
use Lahatre\Master\Http\Requests\UnitFilterRequest;
use Lahatre\Master\Http\Requests\UnitUpsertRequest;
use Lahatre\Master\Http\Resources\UnitCollection;
use Lahatre\Master\Http\Resources\UnitGroupResource;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Services\UnitService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class UnitController
{
    public function __construct(
        protected UnitService $unitService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(UnitFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Unit::class);

        $filters = UnitFilterData::fromArray($request->validated());

        $response = $this->unitService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => UnitCollection::make($response));
    }

    public function upsert(UnitUpsertRequest $request): JsonResponse|Response
    {
        Gate::authorize('upsert', Unit::class);

        $data = UnitUpsertData::fromArray($request->validated());

        $response = $this->unitService->upsert($data);

        return $this->responseResponder->respond(fn (): JsonResource => UnitGroupResource::make($response));
    }
}
