<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Data\CheckoutData;
use Lahatre\Billing\Data\CheckoutFilterData;
use Lahatre\Billing\Http\Requests\CheckoutCreateRequest;
use Lahatre\Billing\Http\Requests\CheckoutFilterRequest;
use Lahatre\Billing\Http\Resources\CheckoutResource;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Services\CheckoutService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class CheckoutController
{
    public function __construct(private CheckoutService $checkoutService) {}

    public function index(CheckoutFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Checkout::class);
        $response = $this->checkoutService->getCheckouts(currentOrganizationId(), CheckoutFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, CheckoutResource::class);
    }

    public function store(CheckoutCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('pay', Subscription::class);
        $checkout = $this->checkoutService->start(currentOrganizationId(), CheckoutData::fromArray($request->validated()));

        return ApiResponse::item($checkout, CheckoutResource::class, 201);
    }

    public function show(Checkout $checkout): JsonResponse|Response
    {
        Gate::authorize('retrieve', $checkout);

        return ApiResponse::item($checkout, CheckoutResource::class);
    }
}
