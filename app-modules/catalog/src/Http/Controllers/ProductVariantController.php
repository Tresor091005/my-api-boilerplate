<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Http\Requests\ProductVariantFilterRequest;
use Lahatre\Catalog\Http\Requests\StoreProductVariantRequest;
use Lahatre\Catalog\Http\Requests\UpdateProductVariantRequest;
use Lahatre\Catalog\Http\Resources\ProductVariantCollection;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductVariantService;

class ProductVariantController
{
    public function __construct(
        protected ProductVariantService $productVariantService
    ) {}

    public function index(ProductVariantFilterRequest $request, Product $product): ProductVariantCollection
    {
        Gate::authorize('retrieve', $product);
        Gate::authorize('list', ProductVariant::class);

        $filters = ProductVariantFilterData::fromArray($request->validated());

        return $this->productVariantService->list($product, $filters);
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('retrieve', $product);
        Gate::authorize('retrieve', $variant);

        $response = $this->productVariantService->retrieve($product, $variant);

        return response()->json($response);
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('create', ProductVariant::class);

        $data = ProductVariantBatchData::fromArray($request->validated());

        $response = $this->productVariantService->create($product, $data);

        return response()->json($response, 201);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('update', $variant);

        $data = ProductVariantUpdateData::fromArray(
            $request->validated(),
            missingFields: ['sku', 'is_active', 'options'],
        );

        $response = $this->productVariantService->update($product, $variant, $data);

        return response()->json($response);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('update', $product);
        Gate::authorize('delete', $variant);

        $this->productVariantService->delete($product, $variant);

        return response()->json(null, 204);
    }
}
