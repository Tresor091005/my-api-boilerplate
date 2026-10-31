<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFilterData;
use Lahatre\Billing\Http\Requests\PlanFilterRequest;
use Lahatre\Billing\Http\Requests\PlanRequest;
use Lahatre\Billing\Http\Resources\PlanResource;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Services\PlanService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class PlanController
{
    public function __construct(
        private PlanService $planService,
    ) {}

    public function index(PlanFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Plan::class);
        $filters = PlanFilterData::fromArray($request->validated());
        $response = $this->planService->paginate($filters);

        return ApiResponse::cursorPage($response, PlanResource::class);
    }

    public function store(PlanRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Plan::class);
        $data = PlanData::fromArray($request->validated());
        $response = $this->planService->create($data);

        return ApiResponse::item($response, PlanResource::class, 201);
    }

    public function show(Plan $plan): JsonResponse|Response
    {
        Gate::authorize('retrieve', $plan);
        $response = $this->planService->retrieve($plan);

        return ApiResponse::item($response, PlanResource::class);
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);
        $data = PlanData::fromArray($request->validated(), missingFields: ['name']);
        $response = $this->planService->update($plan, $data);

        return ApiResponse::item($response, PlanResource::class);
    }

    public function destroy(Plan $plan): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $plan);
        $this->planService->delete($plan);

        return ApiResponse::noContent();
    }
}
