<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Data\PlanVersionFilterData;
use Lahatre\Billing\Http\Requests\PlanVersionFilterRequest;
use Lahatre\Billing\Http\Resources\PlanResource;
use Lahatre\Billing\Http\Resources\PlanVersionResource;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Services\PlanVersionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class PlanLifecycleController
{
    public function __construct(private PlanVersionService $planVersionService) {}

    public function publish(Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);
        $response = $this->planVersionService->publish($plan);

        return ApiResponse::item($response, PlanResource::class);
    }

    public function archive(Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);
        $response = $this->planVersionService->archive($plan);

        return ApiResponse::item($response, PlanResource::class);
    }

    public function versions(PlanVersionFilterRequest $request, Plan $plan): JsonResponse|Response
    {
        Gate::authorize('retrieve', $plan);
        $response = $this->planVersionService->getVersions($plan, PlanVersionFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, PlanVersionResource::class);
    }
}
