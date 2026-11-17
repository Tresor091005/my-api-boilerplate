<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\Sale\Data\SaleData;
use Lahatre\Sale\Data\SaleFilterData;
use Lahatre\Sale\Http\Requests\SaleConfirmRequest;
use Lahatre\Sale\Http\Requests\SaleCreateRequest;
use Lahatre\Sale\Http\Requests\SaleFilterRequest;
use Lahatre\Sale\Http\Requests\SaleReasonRequest;
use Lahatre\Sale\Http\Requests\SaleRejectRequest;
use Lahatre\Sale\Http\Requests\SaleUpdateRequest;
use Lahatre\Sale\Http\Requests\SaleValidateRequest;
use Lahatre\Sale\Http\Resources\SaleResource;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Services\SaleService;
use Lahatre\Sale\Services\SaleWorkflowService;
use Symfony\Component\HttpFoundation\Response;

final readonly class SaleController
{
    public function __construct(
        private SaleService $saleService,
        private SaleWorkflowService $saleWorkflowService,
    ) {}

    public function index(SaleFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Sale::class);

        $sales = $this->saleService->paginate(SaleFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($sales, SaleResource::class);
    }

    public function store(SaleCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Sale::class);
        $sale = $this->saleService->create(SaleData::fromArray($request->validated()));

        return ApiResponse::item($sale, SaleResource::class, 201);
    }

    public function show(Sale $sale): JsonResponse|Response
    {
        Gate::authorize('retrieve', $sale);

        return ApiResponse::item($this->saleService->retrieve($sale), SaleResource::class);
    }

    public function update(SaleUpdateRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('update', $sale);
        $sale = $this->saleService->replace($sale, SaleData::fromArray($request->validated()));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function validate(SaleValidateRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('update', $sale);
        $sale = $this->saleWorkflowService->validate($sale, (int) $request->validated('version'), Gate::allows('approve', $sale));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function reopen(Sale $sale): JsonResponse|Response
    {
        Gate::authorize('update', $sale);
        $sale = $this->saleWorkflowService->reopen($sale);

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function abandon(SaleReasonRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('update', $sale);
        $sale = $this->saleWorkflowService->abandon($sale, $request->validated('reason'));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function approve(Sale $sale): JsonResponse|Response
    {
        Gate::authorize('approve', $sale);
        $sale = $this->saleWorkflowService->approve($sale);

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function reject(SaleRejectRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('approve', $sale);
        $sale = $this->saleWorkflowService->reject($sale, (string) $request->validated('reason'));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function confirm(SaleConfirmRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('confirm', $sale);
        $sale = $this->saleWorkflowService->confirm($sale, (bool) $request->validated('keep_approved_prices', false));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function cancel(SaleReasonRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('cancel', $sale);
        $sale = $this->saleWorkflowService->cancel($sale, $request->validated('reason'));

        return ApiResponse::item($sale, SaleResource::class);
    }

    public function resume(SaleReasonRequest $request, Sale $sale): JsonResponse|Response
    {
        Gate::authorize('cancel', $sale);
        $sale = $this->saleWorkflowService->resume($sale, $request->validated('reason'));

        return ApiResponse::item($sale, SaleResource::class);
    }
}
