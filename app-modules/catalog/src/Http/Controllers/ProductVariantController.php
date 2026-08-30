<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Http\Requests\ProductVariantCreateRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantFilterRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantUpdateRequest;
use Lahatre\Catalog\Http\Resources\ProductVariantCollection;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantController
{
    public function __construct(
        protected ProductVariantService $productVariantService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(ProductVariantFilterRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('retrieve', $product);
        Gate::authorize('list', ProductVariant::class);

        $filters = ProductVariantFilterData::fromArray($request->validated());

        $variants = $this->productVariantService->paginate($product, $filters);

        return $this->responseResponder->respond(
            fn (): JsonResource => ProductVariantCollection::make($variants),
        );
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse|Response
    {
        Gate::authorize('retrieve', $product);
        Gate::authorize('retrieve', $variant);

        $variant = $this->productVariantService->retrieve($product, $variant);

        return $this->responseResponder->respond(
            fn (): JsonResource => ProductVariantResource::make($variant),
        );
    }

    public function store(ProductVariantCreateRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('update', $product);
        Gate::authorize('create', ProductVariant::class);

        $data = ProductVariantBatchData::fromArray($request->validated());

        $variants = $this->productVariantService->create($product, $data);

        return $this->responseResponder->respond(
            fn (): JsonResource => ProductVariantCollection::make($variants),
            status: 201,
        );
    }

    public function update(ProductVariantUpdateRequest $request, Product $product, ProductVariant $variant): JsonResponse|Response
    {
        Gate::authorize('update', $product);
        Gate::authorize('update', $variant);

        $data = ProductVariantUpdateData::fromArray(
            $request->validated(),
            missingFields: ['sku', 'is_active', 'options', 'inventory'],
        );

        $variant = $this->productVariantService->update($product, $variant, $data);

        return $this->responseResponder->respond(
            fn (): JsonResource => ProductVariantResource::make($variant),
        );
    }

    public function destroy(Product $product, ProductVariant $variant): Response
    {
        Gate::authorize('update', $product);
        Gate::authorize('delete', $variant);

        $this->productVariantService->delete($product, $variant);

        return response()->noContent();
    }
}
