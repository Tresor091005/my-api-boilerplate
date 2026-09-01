<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\BundleFilterData;
use Lahatre\Catalog\Http\Requests\BundleCreateRequest;
use Lahatre\Catalog\Http\Requests\BundleFilterRequest;
use Lahatre\Catalog\Http\Requests\BundleUpdateRequest;
use Lahatre\Catalog\Http\Resources\BundleCollection;
use Lahatre\Catalog\Http\Resources\BundleResource;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Services\BundleService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class BundleController
{
    public function __construct(
        private BundleService $bundleService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(BundleFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Bundle::class);
        $bundles = $this->bundleService->paginate(BundleFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => BundleCollection::make($bundles),
        );
    }

    public function store(BundleCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Bundle::class);
        $bundle = $this->bundleService->create(BundleData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => BundleResource::make($bundle),
            status: 201,
        );
    }

    public function show(Bundle $bundle): JsonResponse|Response
    {
        Gate::authorize('retrieve', $bundle);
        $bundle = $this->bundleService->retrieve($bundle);

        return $this->responseResponder->respond(
            fn (): JsonResource => BundleResource::make($bundle),
        );
    }

    public function update(BundleUpdateRequest $request, Bundle $bundle): JsonResponse|Response
    {
        Gate::authorize('update', $bundle);
        $data = BundleData::fromArray(
            $request->validated(),
            missingFields: ['name', 'sku', 'is_active', 'items', 'inventory'],
        );
        $bundle = $this->bundleService->update($bundle, $data);

        return $this->responseResponder->respond(
            fn (): JsonResource => BundleResource::make($bundle),
        );
    }

    public function destroy(Bundle $bundle): Response
    {
        Gate::authorize('delete', $bundle);
        $this->bundleService->delete($bundle);

        return response()->noContent();
    }
}
