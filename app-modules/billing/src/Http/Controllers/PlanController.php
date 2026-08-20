<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFilterData;
use Lahatre\Billing\Http\Requests\PlanFilterRequest;
use Lahatre\Billing\Http\Requests\PlanRequest;
use Lahatre\Billing\Http\Resources\PlanCollection;
use Lahatre\Billing\Http\Resources\PlanResource;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Services\PlanService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final class PlanController
{
    public function __construct(
        protected PlanService $planService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(PlanFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Plan::class);

        $response = $this->planService->paginate(PlanFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => PlanCollection::make($response));
    }

    public function show(Plan $plan): JsonResponse|Response
    {
        Gate::authorize('retrieve', $plan);

        return $this->responseResponder->respond(
            fn (): JsonResource => PlanResource::make($this->planService->retrieve($plan)),
        );
    }

    public function store(PlanRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Plan::class);

        $plan = $this->planService->create(PlanData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => PlanResource::make($plan),
            status: 201,
        );
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);

        $data = PlanData::fromArray($request->validated(), missingFields: ['name', 'is_active']);

        return $this->responseResponder->respond(
            fn (): JsonResource => PlanResource::make($this->planService->update($plan, $data)),
        );
    }

    public function destroy(Plan $plan): Response
    {
        Gate::authorize('delete', $plan);
        $this->planService->delete($plan);

        return response()->noContent();
    }
}
