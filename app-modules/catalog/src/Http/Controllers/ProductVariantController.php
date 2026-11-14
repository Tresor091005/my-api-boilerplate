<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ProductVariantActivationData;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Http\Requests\ProductVariantActivationRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantCreateRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantFilterRequest;
use Lahatre\Catalog\Http\Requests\ProductVariantUpdateRequest;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantController
{
    public function __construct(
        protected ProductVariantService $productVariantService,
    ) {}

    public function index(ProductVariantFilterRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('retrieve', $product);

        $filters = ProductVariantFilterData::fromArray($request->validated());

        $variants = $this->productVariantService->paginate($product, $filters);

        return ApiResponse::cursorPage($variants, ProductVariantResource::class);
    }

    public function updateActivation(ProductVariantActivationRequest $request, Product $product): Response
    {
        Gate::authorize('updateVariant', $product);

        $this->productVariantService->updateActivation(
            $product,
            ProductVariantActivationData::fromArray($request->validated()),
        );

        return ApiResponse::noContent();
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse|Response
    {
        Gate::authorize('retrieve', $product);

        $variant = $this->productVariantService->retrieve($product, $variant);

        return ApiResponse::item($variant, ProductVariantResource::class);
    }

    public function store(ProductVariantCreateRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('createVariant', $product);

        $data = ProductVariantBatchData::fromArray($request->validated());

        $variants = $this->productVariantService->create($product, $data);

        return ApiResponse::list($variants, ProductVariantResource::class, 201);
    }

    public function update(ProductVariantUpdateRequest $request, Product $product, ProductVariant $variant): JsonResponse|Response
    {
        Gate::authorize('updateVariant', $product);

        $data = ProductVariantUpdateData::fromArray(
            $request->validated(),
            missingFields: ['sku', 'tax_profile_id', 'is_active', 'options', 'inventory'],
        );

        $variant = $this->productVariantService->update($product, $variant, $data);

        return ApiResponse::item($variant, ProductVariantResource::class);
    }

    public function destroy(Product $product, ProductVariant $variant): Response
    {
        Gate::authorize('deleteVariant', $product);

        $this->productVariantService->delete($product, $variant);

        return ApiResponse::noContent();
    }
}
