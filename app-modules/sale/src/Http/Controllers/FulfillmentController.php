<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\Sale\Data\FulfillmentFilterData;
use Lahatre\Sale\Data\FulfillmentLineData;
use Lahatre\Sale\Http\Requests\FulfillmentFilterRequest;
use Lahatre\Sale\Http\Requests\FulfillmentRequest;
use Lahatre\Sale\Http\Resources\FulfillmentResource;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Services\FulfillmentService;
use Symfony\Component\HttpFoundation\Response;

/** Reading a fulfillment needs reading its sale too. */
final readonly class FulfillmentController
{
    public function __construct(
        private FulfillmentService $fulfillmentService,
    ) {}

    public function index(FulfillmentFilterRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('list', Fulfillment::class);

        return ApiResponse::cursorPage(
            $this->fulfillmentService->paginate($sale, FulfillmentFilterData::fromArray($request->validated())),
            FulfillmentResource::class,
        );
    }

    public function store(FulfillmentRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('create', Fulfillment::class);
        $fulfillment = $this->fulfillmentService->create($sale, FulfillmentLineData::collect($request->validated('lines')));

        return ApiResponse::item($fulfillment, FulfillmentResource::class, 201);
    }

    public function show(Sale $sale, Fulfillment $fulfillment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('retrieve', $fulfillment);

        return ApiResponse::item($fulfillment, FulfillmentResource::class);
    }

    public function update(FulfillmentRequest $request, Sale $sale, Fulfillment $fulfillment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('update', $fulfillment);
        $fulfillment = $this->fulfillmentService->replace($sale, $fulfillment, FulfillmentLineData::collect($request->validated('lines')));

        return ApiResponse::item($fulfillment, FulfillmentResource::class);
    }

    public function destroy(Sale $sale, Fulfillment $fulfillment): Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('delete', $fulfillment);
        $this->fulfillmentService->delete($sale, $fulfillment);

        return response()->noContent();
    }

    public function complete(Sale $sale, Fulfillment $fulfillment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('complete', $fulfillment);

        return ApiResponse::item($this->fulfillmentService->complete($sale, $fulfillment), FulfillmentResource::class);
    }

    public function cancel(Sale $sale, Fulfillment $fulfillment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);
        Gate::authorize('cancel', $fulfillment);

        return ApiResponse::item($this->fulfillmentService->cancel($sale, $fulfillment), FulfillmentResource::class);
    }
}
