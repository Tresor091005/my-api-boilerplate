<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Data\PlanFeatureGrantsData;
use Lahatre\Billing\Data\PlanTermsData;
use Lahatre\Billing\Http\Requests\PlanDraftFeatureCreateRequest;
use Lahatre\Billing\Http\Requests\PlanDraftFeatureDeleteRequest;
use Lahatre\Billing\Http\Requests\PlanDraftRequest;
use Lahatre\Billing\Http\Resources\PlanResource;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Services\PlanVersionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class PlanDraftController
{
    public function __construct(private PlanVersionService $planVersionService) {}

    public function update(PlanDraftRequest $request, Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);
        $terms = PlanTermsData::fromArray($request->validated(), missingFields: ['price', 'currency_code', 'billing_interval_months']);
        $response = $this->planVersionService->updateDraft($plan, $terms);

        return ApiResponse::item($response, PlanResource::class);
    }

    public function destroy(Plan $plan): Response
    {
        Gate::authorize('update', $plan);
        $this->planVersionService->discardDraft($plan);

        return ApiResponse::noContent();
    }

    public function addFeatures(PlanDraftFeatureCreateRequest $request, Plan $plan): JsonResponse|Response
    {
        Gate::authorize('update', $plan);
        $response = $this->planVersionService->addFeatures($plan, PlanFeatureGrantsData::fromArray($request->validated()));

        return ApiResponse::item($response, PlanResource::class);
    }

    public function removeFeatures(PlanDraftFeatureDeleteRequest $request, Plan $plan): Response
    {
        Gate::authorize('update', $plan);
        $this->planVersionService->removeFeatures($plan, $request->validated('feature_keys'));

        return ApiResponse::noContent();
    }
}
