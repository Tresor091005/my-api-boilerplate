<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Data\TaxFilterData;
use Lahatre\Tax\Http\Requests\TaxFilterRequest;
use Lahatre\Tax\Http\Requests\TaxRequest;
use Lahatre\Tax\Http\Resources\TaxResource;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Services\TaxService;
use Symfony\Component\HttpFoundation\Response;

final readonly class TaxController
{
    public function __construct(
        private TaxService $taxService,
    ) {}

    public function index(TaxFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Tax::class);
        $taxes = $this->taxService->paginate(TaxFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($taxes, TaxResource::class);
    }

    public function store(TaxRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Tax::class);
        $tax = $this->taxService->create(TaxData::fromArray($request->validated()));

        return ApiResponse::item($tax, TaxResource::class, 201);
    }

    public function show(Tax $tax): JsonResponse|Response
    {
        Gate::authorize('retrieve', $tax);
        $tax = $this->taxService->retrieve($tax);

        return ApiResponse::item($tax, TaxResource::class);
    }

    public function update(TaxRequest $request, Tax $tax): JsonResponse|Response
    {
        Gate::authorize('update', $tax);
        $tax = $this->taxService->update($tax, TaxData::fromArray($request->validated()));

        return ApiResponse::item($tax, TaxResource::class);
    }

    public function destroy(Tax $tax): Response
    {
        Gate::authorize('delete', $tax);
        $this->taxService->delete($tax);

        return ApiResponse::noContent();
    }
}
